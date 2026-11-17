<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    Award,
    BarChart3,
    Boxes,
    Calendar,
    CheckCircle2,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Droplets,
    HandCoins,
    MapPin,
    PackageOpen,
    RefreshCw,
    SlidersHorizontal,
    Store,
    Tag,
    TrendingDown,
    TrendingUp,
    Truck,
    Users,
    Wallet,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    meta: {
        type: Object,
        required: true,
    },
    kpis: {
        type: Object,
        required: true,
    },
    weeklyChart: {
        type: Object,
        required: true,
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    channels: {
        type: Object,
        required: true,
    },
    monthlyFinance: {
        type: Object,
        required: true,
    },
    operations: {
        type: Object,
        required: true,
    },
    topCustomersMonth: {
        type: Array,
        default: () => [],
    },
});

const chartPeriod = ref('this_week'); // 'this_week' | 'last_7_days'
const chartMetric = ref('revenue');   // 'revenue' | 'bottles'
const hoveredIndex = ref(null);

const timeGreeting = computed(() => {
    const hourStr = String(props.meta?.updated_at || '').split(':')[0];
    const hour = Number.parseInt(hourStr, 10);
    if (!Number.isNaN(hour)) {
        if (hour < 12) return 'Buenos días,';
        if (hour < 19) return 'Buenas tardes,';
        return 'Buenas noches,';
    }
    return 'Bienvenido,';
});

const formatCurrency = (val) => {
    const num = Number(val || 0);
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};

const formatCompactCurrency = (val) => {
    const num = Number(val || 0);
    if (num >= 1000) {
        return '$' + (num / 1000).toFixed(num % 1000 === 0 ? 0 : 1) + 'k';
    }
    return '$' + Math.round(num);
};

// Genera ruta SVG suave (curvas Bézier cúbicas) para los mini sparklines de las KPI cards
const buildSparklinePaths = (values = []) => {
    const width = 220;
    const height = 44;
    const padX = 4;
    const padTop = 7;
    const padBottom = 7;

    const data = Array.isArray(values) && values.length > 1
        ? values.map((v) => Number(v || 0))
        : [0, 0, 0, 0, 0, 0, 0];

    const minVal = Math.min(...data);
    const maxVal = Math.max(...data);
    const range = maxVal - minVal;

    const points = data.map((val, idx) => {
        const x = padX + (idx * (width - padX * 2)) / (data.length - 1);
        let y;
        if (range === 0) {
            y = val === 0 ? height - padBottom - 2 : height / 2;
        } else {
            const normalized = (val - minVal) / range;
            y = height - padBottom - normalized * (height - padTop - padBottom);
        }
        return { x: Number(x.toFixed(2)), y: Number(y.toFixed(2)) };
    });

    let line = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        const cx = ((p0.x + p1.x) / 2).toFixed(2);
        line += ` C ${cx} ${p0.y}, ${cx} ${p1.y}, ${p1.x} ${p1.y}`;
    }

    const last = points[points.length - 1];
    const first = points[0];
    const area = `${line} L ${last.x} ${height} L ${first.x} ${height} Z`;

    return { line, area };
};

const revenueSpark = computed(() => buildSparklinePaths(props.kpis.revenue_sparkline));
const ordersSpark = computed(() => buildSparklinePaths(props.kpis.orders_sparkline));
const bottlesSpark = computed(() => buildSparklinePaths(props.kpis.bottles_sparkline));
const customersSpark = computed(() => buildSparklinePaths(props.kpis.customers_sparkline));

// Datos activos de la gráfica principal
const activeSeries = computed(() => {
    return props.weeklyChart[chartPeriod.value] || [];
});

const hasWeeklyActivity = computed(() => {
    return activeSeries.value.some((d) =>
        chartMetric.value === 'revenue'
            ? Number(d.total_revenue || 0) > 0
            : Number(d.total_bottles || 0) > 0
    );
});

const seriesMaxValue = computed(() => {
    const vals = activeSeries.value.map((d) =>
        chartMetric.value === 'revenue' ? Number(d.total_revenue || 0) : Number(d.total_bottles || 0)
    );
    const rawMax = Math.max(...vals, 0);
    if (rawMax <= 0) return chartMetric.value === 'revenue' ? 1000 : 20;
    if (chartMetric.value === 'revenue') {
        const step = rawMax > 4000 ? 1000 : rawMax > 1000 ? 500 : 250;
        return Math.ceil((rawMax * 1.15) / step) * step;
    }
    const step = rawMax > 100 ? 25 : 10;
    return Math.ceil((rawMax * 1.15) / step) * step;
});

const yAxisTicks = computed(() => {
    const max = seriesMaxValue.value;
    return [max, max * 0.66, max * 0.33, 0].map((v) => Math.round(v));
});

const defaultFocusIndex = computed(() => {
    const arr = activeSeries.value;
    if (!arr.length) return 0;
    const todayIdx = arr.findIndex((d) => d.is_today);
    if (todayIdx !== -1) return todayIdx;
    return arr.length - 1;
});

const selectedBarIndex = computed(() => {
    return hoveredIndex.value !== null ? hoveredIndex.value : defaultFocusIndex.value;
});

const selectedBarData = computed(() => {
    return activeSeries.value[selectedBarIndex.value] || null;
});

const periodTotalRevenue = computed(() => {
    return activeSeries.value.reduce((acc, item) => acc + Number(item.total_revenue || 0), 0);
});

const periodTotalBottles = computed(() => {
    return activeSeries.value.reduce((acc, item) => acc + Number(item.total_bottles || 0), 0);
});

const getBarHeightPct = (item) => {
    const val = chartMetric.value === 'revenue' ? Number(item.total_revenue || 0) : Number(item.total_bottles || 0);
    if (val <= 0 || seriesMaxValue.value <= 0) return 5;
    return Math.max(9, Math.min(100, Math.round((val / seriesMaxValue.value) * 100)));
};

// Canales de venta Garrafón 20L (único formato)
const totalChannelBottles = computed(() => {
    return (
        Number(props.channels.counter_bottles || 0) +
        Number(props.channels.exchange_bottles || 0) +
        Number(props.channels.fixed_bottles || 0)
    );
});

const channelItems = computed(() => {
    const denom = Math.max(totalChannelBottles.value, 1);
    return [
        {
            key: 'counter',
            label: 'Mostrador en Planta',
            bottles: Number(props.channels.counter_bottles || 0),
            revenue: Number(props.channels.counter_revenue || 0),
            pct: totalChannelBottles.value > 0 ? Math.round((Number(props.channels.counter_bottles || 0) / denom) * 100) : 0,
            barColor: 'bg-gradient-to-r from-[#0071BA] to-[#38BDF8]',
            badgeClass: 'bg-[#0071BA] text-white shadow-sm',
            icon: Store,
        },
        {
            key: 'exchange',
            label: 'Reparto — Cambios',
            bottles: Number(props.channels.exchange_bottles || 0),
            revenue: Number(props.channels.exchange_revenue || 0),
            pct: totalChannelBottles.value > 0 ? Math.round((Number(props.channels.exchange_bottles || 0) / denom) * 100) : 0,
            barColor: 'bg-gradient-to-r from-[#1E1545] to-[#5B45B5]',
            badgeClass: 'bg-[#1E1545] text-white shadow-sm',
            icon: RefreshCw,
        },
        {
            key: 'fixed',
            label: 'Reparto — Fijos',
            bottles: Number(props.channels.fixed_bottles || 0),
            revenue: Number(props.channels.fixed_revenue || 0),
            pct: totalChannelBottles.value > 0 ? Math.round((Number(props.channels.fixed_bottles || 0) / denom) * 100) : 0,
            barColor: 'bg-gradient-to-r from-[#26845B] to-[#34D399]',
            badgeClass: 'bg-[#26845B] text-white shadow-sm',
            icon: Tag,
        },
    ];
});

// Donut Balance Financiero del Mes (SVG r=42)
const donutMetrics = computed(() => {
    const route = Number(props.monthlyFinance.route_revenue || 0);
    const counter = Number(props.monthlyFinance.counter_revenue || 0);
    const pending = Number(props.monthlyFinance.pending_receivable || 0);
    const grand = route + counter + pending;
    const circumference = 2 * Math.PI * 42;

    if (grand <= 0) {
        return {
            hasData: false,
            collectedPct: 0,
            routeDash: 0,
            counterDash: 0,
            pendingDash: 0,
            counterOffset: 0,
            pendingOffset: 0,
            circumference,
        };
    }

    const collectedPct = Math.round(((route + counter) / grand) * 100);
    const routeLen = (route / grand) * circumference;
    const counterLen = (counter / grand) * circumference;
    const pendingLen = (pending / grand) * circumference;

    return {
        hasData: true,
        collectedPct,
        routeDash: routeLen,
        counterDash: counterLen,
        pendingDash: pendingLen,
        counterOffset: -routeLen,
        pendingOffset: -(routeLen + counterLen),
        circumference,
    };
});

// Meta inventario 20L
const targetCoveragePct = computed(() => {
    const active = Number(props.operations.inventory?.active_total || 0);
    const target = Number(props.operations.inventory?.target_quantity || 0);
    if (target <= 0) return 0;
    return Math.min(100, Math.round((active / target) * 100));
});

const getOrderStatusMeta = (status) => {
    switch (status) {
        case 'delivered':
            return {
                badge: 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25',
                iconWrap: 'bg-[#26845B] text-white',
                icon: CheckCircle2,
            };
        case 'in_route':
            return {
                badge: 'bg-[#E5F4FD] text-[#0071BA] border-[#0071BA]/25',
                iconWrap: 'bg-[#0071BA] text-white',
                icon: Truck,
            };
        case 'confirmed':
            return {
                badge: 'bg-[#EEEAF7] text-[#1E1545] border-[#1E1545]/20',
                iconWrap: 'bg-[#1E1545] text-white',
                icon: ClipboardCheck,
            };
        case 'in_process':
            return {
                badge: 'bg-[#E0F2FE] text-[#0284C7] border-[#0284C7]/25',
                iconWrap: 'bg-[#0284C7] text-white',
                icon: Droplets,
            };
        case 'cancelled':
        case 'incident':
            return {
                badge: 'bg-[#FCECEF] text-[#E82147] border-[#E82147]/25',
                iconWrap: 'bg-[#E82147] text-white',
                icon: XCircle,
            };
        default:
            return {
                badge: 'bg-[#FEF3C7] text-[#B45309] border-[#D99A27]/30',
                iconWrap: 'bg-[#D99A27] text-white',
                icon: Clock,
            };
    }
};

const pipelineStages = computed(() => [
    {
        key: 'pending',
        label: 'Pendientes',
        value: props.operations.pipeline.pending,
        bg: 'bg-[#FEF3C7]/70 border-[#D99A27]/30',
        text: 'text-[#B45309]',
        chipBg: 'bg-[#D99A27] text-white',
        icon: Clock,
    },
    {
        key: 'confirmed',
        label: 'Confirmados',
        value: props.operations.pipeline.confirmed,
        bg: 'bg-[#EEEAF7] border-[#1E1545]/20',
        text: 'text-[#1E1545]',
        chipBg: 'bg-[#1E1545] text-white',
        icon: ClipboardCheck,
    },
    {
        key: 'in_route',
        label: 'En ruta',
        value: props.operations.pipeline.in_route,
        bg: 'bg-[#E5F4FD] border-[#0071BA]/25',
        text: 'text-[#0071BA]',
        chipBg: 'bg-[#0071BA] text-white',
        icon: Truck,
    },
    {
        key: 'in_process',
        label: 'En proceso',
        value: props.operations.pipeline.in_process,
        bg: 'bg-[#E0F2FE]/80 border-[#0284C7]/25',
        text: 'text-[#0284C7]',
        chipBg: 'bg-[#0284C7] text-white',
        icon: Droplets,
    },
    {
        key: 'delivered_today',
        label: 'Entregados hoy',
        value: props.operations.pipeline.delivered_today,
        bg: 'bg-[#E6F7EE] border-[#26845B]/25',
        text: 'text-[#26845B]',
        chipBg: 'bg-[#26845B] text-white',
        icon: CheckCircle2,
    },
]);

const quickActions = [
    {
        label: 'Pedidos',
        sub: 'Supervisión global',
        href: route('admin.orders.index'),
        icon: ClipboardList,
        iconClass: 'bg-[#0071BA] text-white',
        hoverBorder: 'hover:border-[#0071BA]/45',
    },
    {
        label: 'Por confirmar',
        sub: 'Pedidos pendientes',
        href: route('admin.orders.index', { status: 'pending' }),
        icon: Clock,
        iconClass: 'bg-[#1E1545] text-white',
        hoverBorder: 'hover:border-[#1E1545]/45',
    },
    {
        label: 'Inventario 20L',
        sub: 'Planta y camión',
        href: route('admin.exchange-inventory.index'),
        icon: Boxes,
        iconClass: 'bg-[#26845B] text-white',
        hoverBorder: 'hover:border-[#26845B]/45',
    },
    {
        label: 'Clientes',
        sub: 'Directorio y zonas',
        href: route('admin.customers.index'),
        icon: MapPin,
        iconClass: 'bg-[#D99A27] text-white',
        hoverBorder: 'hover:border-[#D99A27]/50',
    },
    {
        label: 'Usuarios',
        sub: 'Personal y accesos',
        href: route('admin.users.index'),
        icon: Users,
        iconClass: 'bg-[#E82147] text-white',
        hoverBorder: 'hover:border-[#E82147]/45',
    },
    {
        label: 'Configuración',
        sub: 'Horarios y precios',
        href: route('admin.settings.index'),
        icon: SlidersHorizontal,
        iconClass: 'bg-[#0071BA] text-white',
        hoverBorder: 'hover:border-[#0071BA]/45',
    },
];
</script>

<template>
    <Head title="Panel Ejecutivo - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1850px] px-4 sm:px-6 lg:px-8 space-y-5 sm:space-y-6">

                <!-- 1. HERO BANNER DE BIENVENIDA CON ILUSTRACIÓN DE PURIFICADORA -->
                <section
                    aria-label="Bienvenida"
                    class="relative overflow-hidden rounded-2xl bg-[#0275B8] shadow-sm min-h-[196px] lg:min-h-[214px] flex items-stretch"
                >
                    <!-- Formas planas sutiles de agua en azul más claro/oscuro (sin degradado) -->
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
                        <circle cx="560" cy="36" r="64" fill="#FFFFFF" fill-opacity="0.06" />
                    </svg>

                    <!-- Contenedor principal en 2 zonas equilibradas -->
                    <div class="relative z-10 w-full grid grid-cols-1 md:grid-cols-12 items-center">
                        <!-- Zona izquierda: Diseño minimalista directo sobre el fondo azul -->
                        <div class="md:col-span-7 lg:col-span-6 p-6 sm:p-8 lg:px-9 space-y-4">
                            <!-- Estado de sucursal y horario diferenciados -->
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
                                    <span>{{ meta.is_store_open ? 'Sucursal abierta' : 'Fuera de horario' }}</span>
                                </span>

                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-semibold bg-[#1E1545] text-white tabular-nums shadow-sm">
                                    <Clock class="w-3.5 h-3.5 text-[#7DD3FC]" :stroke-width="2" />
                                    <span>{{ meta.schedule_hours }}</span>
                                </span>
                            </div>

                            <!-- Saludo con barra lateral de acento -->
                            <div class="pl-3.5 border-l-4 border-[#1E1545]">
                                <p class="text-xs sm:text-sm font-semibold uppercase tracking-wider text-[#BAE6FD]">
                                    {{ timeGreeting }}
                                </p>
                                <h1 class="text-2xl sm:text-[30px] font-bold tracking-tight text-white leading-tight mt-0.5">
                                    {{ $page.props.auth.user.name }}
                                </h1>
                            </div>

                            <!-- Fecha destacada -->
                            <div class="flex items-center">
                                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-[#EEEAF7] text-[#1E1545] shadow-sm">
                                    <Calendar class="w-3.5 h-3.5 text-[#0071BA]" :stroke-width="2" />
                                    <span>{{ meta.date_label }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Zona derecha: Ilustración ampliada y sin cortes -->
                        <div class="hidden md:block md:col-span-5 lg:col-span-6 relative self-stretch min-h-[196px] lg:min-h-[214px]">
                            <img
                                src="/images/dashboard-hero-puritronic.jpg"
                                alt="Equipo de purificadora de agua y garrafones Puritronic"
                                class="absolute right-0 bottom-0 h-[142%] w-auto max-w-none object-contain object-right-bottom pointer-events-none select-none"
                                style="-webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.85) 18%, black 32%); mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,0.85) 18%, black 32%);"
                            />
                        </div>
                    </div>
                </section>

                <!-- 2. INDICADORES PRINCIPALES (4 KPI CARDS COLORIDAS CON SPARKLINES) -->
                <section aria-label="Indicadores principales" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">

                    <!-- KPI 1: Ingresos de hoy -->
                    <article class="bg-white rounded-2xl border border-[#E7E4ED] shadow-sm hover:border-[#26845B]/40 transition-colors pt-5 px-5 pb-3 flex flex-col justify-between overflow-hidden relative">
                        <span class="absolute top-0 inset-x-0 h-1 bg-[#26845B]"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[#777386]">Ingresos de hoy</p>
                                <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-[#211B35] mt-1 tabular-nums truncate">
                                    {{ formatCurrency(kpis.revenue_today) }}
                                </p>
                                <div class="flex items-center gap-1.5 mt-2 text-xs">
                                    <span
                                        v-if="kpis.revenue_growth_pct !== null"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold tabular-nums"
                                        :class="kpis.revenue_growth_pct >= 0 ? 'bg-[#E6F7EE] text-[#26845B]' : 'bg-[#FCECEF] text-[#E82147]'"
                                    >
                                        <TrendingUp v-if="kpis.revenue_growth_pct >= 0" class="w-3 h-3" :stroke-width="2.2" />
                                        <TrendingDown v-else class="w-3 h-3" :stroke-width="2.2" />
                                        {{ kpis.revenue_growth_pct >= 0 ? '+' : '' }}{{ kpis.revenue_growth_pct }}% vs ayer
                                    </span>
                                    <span v-else class="text-[#777386] tabular-nums">
                                        Mostrador {{ formatCurrency(kpis.counter_revenue_today) }}
                                    </span>
                                </div>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#26845B] text-white flex items-center justify-center shadow-sm shrink-0">
                                <Wallet class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                        </div>

                        <div class="mt-3 -mx-1">
                            <svg viewBox="0 0 220 44" class="w-full h-11 overflow-visible" aria-hidden="true">
                                <defs>
                                    <linearGradient id="kpiSparkRev2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#26845B" stop-opacity="0.26" />
                                        <stop offset="100%" stop-color="#26845B" stop-opacity="0.02" />
                                    </linearGradient>
                                </defs>
                                <path :d="revenueSpark.area" fill="url(#kpiSparkRev2)" />
                                <path :d="revenueSpark.line" fill="none" stroke="#26845B" stroke-width="2.25" stroke-linecap="round" />
                            </svg>
                        </div>
                    </article>

                    <!-- KPI 2: Pedidos de hoy -->
                    <article class="bg-white rounded-2xl border border-[#E7E4ED] shadow-sm hover:border-[#0071BA]/40 transition-colors pt-5 px-5 pb-3 flex flex-col justify-between overflow-hidden relative">
                        <span class="absolute top-0 inset-x-0 h-1 bg-[#0071BA]"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[#777386]">Pedidos de hoy</p>
                                <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-[#211B35] mt-1 tabular-nums">
                                    {{ kpis.orders_today }}
                                </p>
                                <div class="flex items-center gap-2 mt-2 text-xs flex-wrap">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[11px] font-semibold tabular-nums"
                                        :class="kpis.orders_diff >= 0 ? 'bg-[#E5F4FD] text-[#0071BA]' : 'bg-[#FEF3C7] text-[#B45309]'"
                                    >
                                        {{ kpis.orders_diff >= 0 ? '+' : '' }}{{ kpis.orders_diff }} vs ayer
                                    </span>
                                    <span
                                        v-if="kpis.pending_orders_count > 0"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#FCECEF] text-[#E82147] tabular-nums"
                                    >
                                        {{ kpis.pending_orders_count }} por confirmar
                                    </span>
                                </div>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#0071BA] text-white flex items-center justify-center shadow-sm shrink-0">
                                <ClipboardCheck class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                        </div>

                        <div class="mt-3 -mx-1">
                            <svg viewBox="0 0 220 44" class="w-full h-11 overflow-visible" aria-hidden="true">
                                <defs>
                                    <linearGradient id="kpiSparkOrd2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0071BA" stop-opacity="0.26" />
                                        <stop offset="100%" stop-color="#0071BA" stop-opacity="0.02" />
                                    </linearGradient>
                                </defs>
                                <path :d="ordersSpark.area" fill="url(#kpiSparkOrd2)" />
                                <path :d="ordersSpark.line" fill="none" stroke="#0071BA" stroke-width="2.25" stroke-linecap="round" />
                            </svg>
                        </div>
                    </article>

                    <!-- KPI 3: Garrafones 20L de hoy -->
                    <article class="bg-white rounded-2xl border border-[#E7E4ED] shadow-sm hover:border-[#1E1545]/35 transition-colors pt-5 px-5 pb-3 flex flex-col justify-between overflow-hidden relative">
                        <span class="absolute top-0 inset-x-0 h-1 bg-[#1E1545]"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[#777386]">Garrafones 20L hoy</p>
                                <div class="flex items-baseline gap-1.5 mt-1">
                                    <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-[#211B35] tabular-nums">
                                        {{ kpis.bottles_today }}
                                    </p>
                                    <span class="text-xs font-medium text-[#777386]">unidades</span>
                                </div>
                                <p class="text-xs text-[#777386] mt-2 tabular-nums truncate">
                                    <span class="font-semibold text-[#1E1545]">{{ kpis.route_bottles_today }}</span> reparto &bull;
                                    <span class="font-semibold text-[#0071BA]">{{ kpis.counter_bottles_today }}</span> mostrador
                                </p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shadow-sm shrink-0">
                                <Droplets class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                        </div>

                        <div class="mt-3 -mx-1">
                            <svg viewBox="0 0 220 44" class="w-full h-11 overflow-visible" aria-hidden="true">
                                <defs>
                                    <linearGradient id="kpiSparkBtl2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#1E1545" stop-opacity="0.24" />
                                        <stop offset="100%" stop-color="#1E1545" stop-opacity="0.02" />
                                    </linearGradient>
                                </defs>
                                <path :d="bottlesSpark.area" fill="url(#kpiSparkBtl2)" />
                                <path :d="bottlesSpark.line" fill="none" stroke="#1E1545" stroke-width="2.25" stroke-linecap="round" />
                            </svg>
                        </div>
                    </article>

                    <!-- KPI 4: Clientes registrados -->
                    <article class="bg-white rounded-2xl border border-[#E7E4ED] shadow-sm hover:border-[#D99A27]/45 transition-colors pt-5 px-5 pb-3 flex flex-col justify-between overflow-hidden relative">
                        <span class="absolute top-0 inset-x-0 h-1 bg-[#D99A27]"></span>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-[#777386]">Clientes registrados</p>
                                <p class="text-2xl sm:text-[26px] font-bold tracking-tight text-[#211B35] mt-1 tabular-nums">
                                    {{ kpis.total_customers }}
                                </p>
                                <div class="flex items-center gap-1.5 mt-2 text-xs flex-wrap">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#E6F7EE] text-[#26845B] tabular-nums">
                                        +{{ kpis.customers_this_month }} este mes
                                    </span>
                                    <span class="text-[#777386] tabular-nums">{{ kpis.customers_with_account }} con cuenta</span>
                                </div>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-[#D99A27] text-white flex items-center justify-center shadow-sm shrink-0">
                                <Users class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                        </div>

                        <div class="mt-3 -mx-1">
                            <svg viewBox="0 0 220 44" class="w-full h-11 overflow-visible" aria-hidden="true">
                                <defs>
                                    <linearGradient id="kpiSparkCus2" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#D99A27" stop-opacity="0.26" />
                                        <stop offset="100%" stop-color="#D99A27" stop-opacity="0.02" />
                                    </linearGradient>
                                </defs>
                                <path :d="customersSpark.area" fill="url(#kpiSparkCus2)" />
                                <path :d="customersSpark.line" fill="none" stroke="#D99A27" stroke-width="2.25" stroke-linecap="round" />
                            </svg>
                        </div>
                    </article>
                </section>

                <!-- 3. INGRESOS DE LA SEMANA + PEDIDOS RECIENTES -->
                <section class="grid grid-cols-1 xl:grid-cols-12 gap-5">

                    <!-- Gráfica semanal -->
                    <div class="xl:col-span-7 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">
                                        {{ chartMetric === 'revenue' ? 'Ingresos de la semana' : 'Garrafones 20L entregados' }}
                                    </h2>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#E5F4FD] text-[#0071BA] border border-[#0071BA]/20 tabular-nums">
                                        {{ chartMetric === 'revenue' ? formatCurrency(periodTotalRevenue) : `${periodTotalBottles} u.` }}
                                    </span>
                                </div>
                                <p class="text-xs text-[#777386] mt-0.5">
                                    Comparativa diaria entre reparto a domicilio y venta en mostrador
                                </p>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <div class="inline-flex p-1 rounded-xl bg-[#F5F4F8] border border-[#E7E4ED]">
                                    <button
                                        type="button"
                                        @click="chartMetric = 'revenue'"
                                        class="px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer"
                                        :class="chartMetric === 'revenue' ? 'bg-[#1E1545] text-white shadow-sm' : 'text-[#777386] hover:text-[#211B35]'"
                                    >
                                        Ingresos
                                    </button>
                                    <button
                                        type="button"
                                        @click="chartMetric = 'bottles'"
                                        class="px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer"
                                        :class="chartMetric === 'bottles' ? 'bg-[#1E1545] text-white shadow-sm' : 'text-[#777386] hover:text-[#211B35]'"
                                    >
                                        20L
                                    </button>
                                </div>

                                <select
                                    v-model="chartPeriod"
                                    aria-label="Seleccionar periodo"
                                    class="py-1.5 pl-3 pr-8 text-xs font-semibold text-[#211B35] bg-[#F5F4F8] border border-[#E7E4ED] rounded-xl focus:border-[#0071BA] focus:ring-2 focus:ring-[#0071BA]/15 transition cursor-pointer"
                                >
                                    <option value="this_week">Esta semana</option>
                                    <option value="last_7_days">Últimos 7 días</option>
                                </select>
                            </div>
                        </div>

                        <!-- Resumen interactivo del día seleccionado -->
                        <div
                            v-if="hasWeeklyActivity && selectedBarData"
                            class="mt-4 px-3.5 py-2.5 rounded-xl bg-[#EEEAF7]/65 border border-[#E7E4ED] flex flex-wrap items-center justify-between gap-3 text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#E82147]"></span>
                                <span class="font-semibold text-[#211B35]">{{ selectedBarData.full_label }}</span>
                                <span v-if="selectedBarData.is_today" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#0071BA] text-white">Hoy</span>
                            </div>
                            <div class="flex items-center gap-4 tabular-nums flex-wrap">
                                <span class="text-[#777386]">
                                    Reparto: <strong class="text-[#1E1545]">{{ chartMetric === 'revenue' ? formatCurrency(selectedBarData.route_revenue) : `${selectedBarData.route_bottles} u.` }}</strong>
                                </span>
                                <span class="text-[#777386]">
                                    Mostrador: <strong class="text-[#0071BA]">{{ chartMetric === 'revenue' ? formatCurrency(selectedBarData.counter_revenue) : `${selectedBarData.counter_bottles} u.` }}</strong>
                                </span>
                                <span class="font-bold text-[#211B35] sm:pl-3 sm:border-l sm:border-[#E7E4ED]">
                                    {{ chartMetric === 'revenue' ? formatCurrency(selectedBarData.total_revenue) : `${selectedBarData.total_bottles} garrafones` }}
                                </span>
                            </div>
                        </div>

                        <!-- Gráfica de barras con datos -->
                        <div v-if="hasWeeklyActivity" class="mt-6 relative flex gap-3 items-stretch h-56 select-none">
                            <div class="flex flex-col justify-between text-[11px] text-[#777386] tabular-nums pb-7 pr-1 text-right min-w-[44px]">
                                <span v-for="(tick, idx) in yAxisTicks" :key="idx">
                                    {{ chartMetric === 'revenue' ? formatCompactCurrency(tick) : tick }}
                                </span>
                            </div>

                            <div class="flex-1 relative flex flex-col min-w-0">
                                <div class="flex-1 relative flex items-end justify-between gap-1.5 sm:gap-4 px-1 sm:px-2 pb-1">
                                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                                        <div v-for="n in 4" :key="n" class="border-t border-dashed border-[#E7E4ED] w-full"></div>
                                    </div>

                                    <div
                                        v-for="(day, idx) in activeSeries"
                                        :key="day.date"
                                        @mouseenter="hoveredIndex = idx"
                                        @mouseleave="hoveredIndex = null"
                                        @click="hoveredIndex = idx"
                                        class="relative z-10 flex-1 h-full flex flex-col justify-end items-center group cursor-pointer"
                                    >
                                        <div
                                            v-if="selectedBarIndex === idx"
                                            class="mb-1.5 px-2.5 py-0.5 rounded-lg bg-[#1E1545] text-white text-[10px] font-semibold whitespace-nowrap shadow-sm tabular-nums"
                                        >
                                            {{ chartMetric === 'revenue' ? formatCurrency(day.total_revenue) : `${day.total_bottles} u.` }}
                                        </div>

                                        <div
                                            class="w-full max-w-[40px] rounded-t-xl transition-all duration-300"
                                            :style="{ height: `${getBarHeightPct(day)}%` }"
                                            :class="selectedBarIndex === idx
                                                ? 'bg-gradient-to-t from-[#1E1545] to-[#0071BA] shadow-sm'
                                                : day.is_today
                                                    ? 'bg-gradient-to-t from-[#0071BA] to-[#38BDF8]'
                                                    : 'bg-gradient-to-t from-[#0071BA]/55 to-[#60A5FA]/70 group-hover:from-[#0071BA] group-hover:to-[#38BDF8]'"
                                        ></div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-1.5 sm:gap-4 px-1 sm:px-2 pt-2.5 border-t border-[#E7E4ED]">
                                    <div
                                        v-for="(day, idx) in activeSeries"
                                        :key="`lbl-${day.date}`"
                                        class="flex-1 text-center text-[11px] font-medium transition-colors"
                                        :class="selectedBarIndex === idx ? 'text-[#1E1545] font-bold' : 'text-[#777386]'"
                                    >
                                        {{ day.short_label }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estado vacío elegante -->
                        <div
                            v-else
                            class="mt-6 flex-1 min-h-[220px] rounded-2xl bg-[#EEEAF7]/45 border border-dashed border-[#1E1545]/20 flex flex-col items-center justify-center p-6 text-center"
                        >
                            <div class="h-11 w-11 rounded-xl bg-[#0071BA] text-white flex items-center justify-center mb-3 shadow-sm">
                                <BarChart3 class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                            <p class="text-sm font-semibold text-[#211B35]">Sin movimientos registrados en este periodo</p>
                            <p class="text-xs text-[#777386] mt-1 max-w-xs">
                                Cuando se registren cobros en ruta o llenados en mostrador, la gráfica se actualizará automáticamente.
                            </p>
                        </div>
                    </div>

                    <!-- Pedidos recientes -->
                    <div class="xl:col-span-5 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div>
                                <h2 class="text-base font-bold text-[#211B35] tracking-tight">Pedidos recientes</h2>
                                <p class="text-xs text-[#777386] mt-0.5">Últimas solicitudes recibidas en el sistema</p>
                            </div>
                            <Link
                                :href="route('admin.orders.index')"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#0071BA] hover:text-[#1E1545] transition shrink-0"
                            >
                                <span>Ver todos</span>
                                <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
                            </Link>
                        </div>

                        <div v-if="recentOrders.length > 0" class="divide-y divide-[#E7E4ED] flex-1">
                            <Link
                                v-for="order in recentOrders"
                                :key="order.id"
                                :href="route('admin.orders.show', order.id)"
                                class="py-3 first:pt-1 last:pb-1 flex items-center justify-between gap-3 hover:bg-[#F5F4F8] -mx-2 px-2.5 rounded-xl transition-colors"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div
                                        class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                                        :class="getOrderStatusMeta(order.status).iconWrap"
                                    >
                                        <component :is="getOrderStatusMeta(order.status).icon" class="w-4 h-4" :stroke-width="2" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-[#1E1545] tabular-nums shrink-0">{{ order.folio }}</span>
                                            <span class="text-xs font-semibold text-[#211B35] truncate">{{ order.customer_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-[11px] text-[#777386] mt-0.5 tabular-nums">
                                            <span>{{ order.time_label }}</span>
                                            <span>&bull;</span>
                                            <span>{{ order.total_bottles }} garrafones 20L</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2.5 shrink-0">
                                    <span
                                        class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                        :class="getOrderStatusMeta(order.status).badge"
                                    >
                                        {{ order.status_label }}
                                    </span>
                                    <span class="text-xs font-bold text-[#211B35] tabular-nums min-w-[66px] text-right">
                                        {{ formatCurrency(order.total_amount) }}
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="flex-1 flex flex-col items-center justify-center py-10 px-4 text-center rounded-2xl bg-[#E5F4FD]/45 border border-dashed border-[#0071BA]/25">
                            <div class="h-11 w-11 rounded-xl bg-[#0071BA] text-white flex items-center justify-center mb-3 shadow-sm">
                                <PackageOpen class="w-5 h-5" :stroke-width="1.9" />
                            </div>
                            <p class="text-sm font-semibold text-[#211B35]">Sin pedidos registrados</p>
                            <p class="text-xs text-[#777386] mt-1 max-w-xs">Los nuevos pedidos realizados por clientes aparecerán aquí automáticamente.</p>
                        </div>
                    </div>
                </section>

                <!-- 4. CANALES DE GARRAFÓN 20L + INGRESOS DEL MES + PLANTA E INVENTARIO 20L -->
                <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

                    <!-- Canales de Garrafón 20L -->
                    <div class="lg:col-span-4 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-5">
                                <div>
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">Canales de Garrafón 20L</h2>
                                    <p class="text-xs text-[#777386] mt-0.5">{{ meta.month_label }}</p>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#E5F4FD] text-[#0071BA] border border-[#0071BA]/20">
                                    Único 20L
                                </span>
                            </div>

                            <div class="space-y-4">
                                <div v-for="ch in channelItems" :key="ch.key" class="space-y-1.5">
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span
                                                class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0"
                                                :class="ch.badgeClass"
                                            >
                                                <component :is="ch.icon" class="w-3.5 h-3.5" :stroke-width="2" />
                                            </span>
                                            <span class="font-semibold text-[#211B35] truncate">{{ ch.label }}</span>
                                        </div>
                                        <div class="text-right tabular-nums shrink-0">
                                            <span class="font-bold text-[#211B35]">{{ ch.bottles }}</span>
                                            <span class="text-[#777386] text-[11px] ml-1">({{ formatCurrency(ch.revenue) }})</span>
                                        </div>
                                    </div>
                                    <div class="h-2.5 w-full rounded-full bg-[#F5F4F8] overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="ch.barColor"
                                            :style="{ width: `${ch.bottles > 0 ? Math.max(6, ch.pct) : 0}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cobertura por Zonas -->
                        <div class="mt-6 pt-4 border-t border-[#E7E4ED]">
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-[#777386]">Cobertura por zona</span>
                                <Link :href="route('admin.customers.index')" class="text-[11px] font-semibold text-[#0071BA] hover:text-[#1E1545] transition">
                                    Ver clientes
                                </Link>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div
                                    v-for="z in channels.zones"
                                    :key="z.key"
                                    class="p-2.5 rounded-xl bg-[#F5F4F8] border border-[#E7E4ED] text-center"
                                >
                                    <div class="flex items-center justify-center gap-1.5 text-[11px] font-semibold text-[#777386]">
                                        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: z.color }"></span>
                                        <span>{{ z.label }}</span>
                                    </div>
                                    <p class="text-base font-bold text-[#211B35] mt-0.5 tabular-nums">{{ z.count }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ingresos del mes (Donut + Balance financiero) -->
                    <div class="lg:col-span-4 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-2 flex-wrap">
                                <div>
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">Ingresos del mes</h2>
                                    <p class="text-2xl font-bold text-[#211B35] mt-1 tabular-nums">
                                        {{ formatCurrency(monthlyFinance.total_revenue) }}
                                    </p>
                                </div>
                                <span
                                    v-if="monthlyFinance.growth_pct !== null"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold border tabular-nums"
                                    :class="monthlyFinance.growth_pct >= 0
                                        ? 'bg-[#E6F7EE] text-[#26845B] border-[#26845B]/25'
                                        : 'bg-[#FCECEF] text-[#E82147] border-[#E82147]/25'"
                                >
                                    <TrendingUp v-if="monthlyFinance.growth_pct >= 0" class="w-3 h-3" :stroke-width="2.2" />
                                    <TrendingDown v-else class="w-3 h-3" :stroke-width="2.2" />
                                    {{ monthlyFinance.growth_pct >= 0 ? '+' : '' }}{{ monthlyFinance.growth_pct }}% vs mes ant.
                                </span>
                            </div>

                            <div class="mt-5 flex flex-col sm:flex-row items-center gap-5">
                                <div class="relative h-28 w-28 shrink-0">
                                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
                                        <circle
                                            cx="50"
                                            cy="50"
                                            r="42"
                                            fill="none"
                                            stroke="#EEEAF7"
                                            stroke-width="12"
                                        />
                                        <circle
                                            v-if="donutMetrics.routeDash > 0"
                                            cx="50"
                                            cy="50"
                                            r="42"
                                            fill="none"
                                            stroke="#1E1545"
                                            stroke-width="12"
                                            stroke-linecap="butt"
                                            :stroke-dasharray="`${donutMetrics.routeDash} ${donutMetrics.circumference}`"
                                        />
                                        <circle
                                            v-if="donutMetrics.counterDash > 0"
                                            cx="50"
                                            cy="50"
                                            r="42"
                                            fill="none"
                                            stroke="#0071BA"
                                            stroke-width="12"
                                            stroke-linecap="butt"
                                            :stroke-dasharray="`${donutMetrics.counterDash} ${donutMetrics.circumference}`"
                                            :stroke-dashoffset="donutMetrics.counterOffset"
                                        />
                                        <circle
                                            v-if="donutMetrics.pendingDash > 0"
                                            cx="50"
                                            cy="50"
                                            r="42"
                                            fill="none"
                                            stroke="#D99A27"
                                            stroke-width="12"
                                            stroke-linecap="butt"
                                            :stroke-dasharray="`${donutMetrics.pendingDash} ${donutMetrics.circumference}`"
                                            :stroke-dashoffset="donutMetrics.pendingOffset"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                        <span class="text-lg font-bold text-[#211B35] tabular-nums leading-none">
                                            {{ donutMetrics.hasData ? `${donutMetrics.collectedPct}%` : '0%' }}
                                        </span>
                                        <span class="text-[10px] font-medium text-[#777386] mt-0.5">
                                            {{ donutMetrics.hasData ? 'Cobrado' : 'Sin cobros' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="w-full flex-1 space-y-2.5 text-xs">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 text-[#777386] font-medium">
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#1E1545] shrink-0"></span>
                                            Reparto
                                        </span>
                                        <span class="font-bold text-[#211B35] tabular-nums">{{ formatCurrency(monthlyFinance.route_revenue) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 text-[#777386] font-medium">
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#0071BA] shrink-0"></span>
                                            Mostrador
                                        </span>
                                        <span class="font-bold text-[#211B35] tabular-nums">{{ formatCurrency(monthlyFinance.counter_revenue) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-[#E7E4ED]">
                                        <span class="flex items-center gap-2 text-[#B45309] font-semibold">
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#D99A27] shrink-0"></span>
                                            Por cobrar
                                        </span>
                                        <span class="font-bold text-[#B45309] tabular-nums">{{ formatCurrency(monthlyFinance.pending_receivable) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-3 rounded-xl bg-[#FEF3C7]/60 border border-[#D99A27]/25 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2 text-[#211B35] min-w-0">
                                <HandCoins class="w-4 h-4 text-[#D99A27] shrink-0" :stroke-width="2" />
                                <span class="truncate font-medium">
                                    <strong class="tabular-nums">{{ monthlyFinance.debtors_count }}</strong>
                                    {{ monthlyFinance.debtors_count === 1 ? 'cliente con saldo' : 'clientes con saldo' }}
                                </span>
                            </div>
                            <span class="text-[11px] font-semibold text-[#777386] tabular-nums shrink-0">
                                Ant: {{ formatCurrency(monthlyFinance.previous_month_revenue) }}
                            </span>
                        </div>
                    </div>

                    <!-- Planta e Inventario 20L + Ruta de hoy -->
                    <div class="lg:col-span-4 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-4">
                                <div>
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">Planta e Inventario 20L</h2>
                                    <p class="text-xs text-[#777386] mt-0.5">Estado físico de botellones en circuito</p>
                                </div>
                                <Link
                                    :href="route('admin.exchange-inventory.index')"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#0071BA] hover:text-[#1E1545] transition shrink-0"
                                >
                                    <span>Supervisar</span>
                                    <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
                                </Link>
                            </div>

                            <!-- Diferenciación visual clara: Llenos / Lavado / Vacíos -->
                            <div class="grid grid-cols-3 gap-2.5">
                                <div class="p-3 rounded-xl bg-[#E6F7EE] border border-[#26845B]/25">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-[#26845B] uppercase tracking-wide">Llenos</span>
                                        <span class="h-6 w-6 rounded-lg bg-[#26845B] text-white flex items-center justify-center">
                                            <CheckCircle2 class="w-3.5 h-3.5" :stroke-width="2.2" />
                                        </span>
                                    </div>
                                    <p class="text-xl font-bold text-[#211B35] mt-1.5 tabular-nums">
                                        {{ operations.inventory.available_full_total }}
                                    </p>
                                    <span class="text-[10px] font-medium text-[#26845B] tabular-nums block mt-0.5">
                                        {{ operations.inventory.plant_full_available }}P / {{ operations.inventory.truck_full_available }}C
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-[#E5F4FD] border border-[#0071BA]/25">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-[#0071BA] uppercase tracking-wide">Lavado</span>
                                        <span class="h-6 w-6 rounded-lg bg-[#0071BA] text-white flex items-center justify-center">
                                            <Droplets class="w-3.5 h-3.5" :stroke-width="2.2" />
                                        </span>
                                    </div>
                                    <p class="text-xl font-bold text-[#211B35] mt-1.5 tabular-nums">
                                        {{ operations.inventory.plant_in_preparation }}
                                    </p>
                                    <span class="text-[10px] font-medium text-[#0071BA] block mt-0.5">En planta</span>
                                </div>

                                <div class="p-3 rounded-xl bg-[#FEF3C7]/85 border border-[#D99A27]/30">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-[#B45309] uppercase tracking-wide">Vacíos</span>
                                        <span class="h-6 w-6 rounded-lg bg-[#D99A27] text-white flex items-center justify-center">
                                            <RefreshCw class="w-3.5 h-3.5" :stroke-width="2.2" />
                                        </span>
                                    </div>
                                    <p class="text-xl font-bold text-[#211B35] mt-1.5 tabular-nums">
                                        {{ operations.inventory.plant_empty_available }}
                                    </p>
                                    <span class="text-[10px] font-medium text-[#B45309] block mt-0.5">Disponibles</span>
                                </div>
                            </div>

                            <!-- Barra de meta -->
                            <div class="mt-4 space-y-1.5">
                                <div class="flex items-center justify-between text-xs gap-2">
                                    <span class="text-[#777386]">
                                        Meta circuito: <strong class="text-[#211B35] tabular-nums">{{ operations.inventory.active_total }} / {{ operations.inventory.target_quantity }}</strong>
                                    </span>
                                    <span
                                        v-if="operations.inventory.pending_incidents_count > 0"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#FCECEF] text-[#E82147] border border-[#E82147]/25 tabular-nums"
                                    >
                                        <AlertTriangle class="w-3 h-3" :stroke-width="2" />
                                        {{ operations.inventory.pending_incidents_count }} incidencias
                                    </span>
                                    <span v-else class="text-[11px] font-bold text-[#26845B] tabular-nums">
                                        {{ targetCoveragePct }}%
                                    </span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-[#F5F4F8] overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="operations.inventory.diff_status === 'below' ? 'bg-[#D99A27]' : 'bg-gradient-to-r from-[#1E1545] to-[#0071BA]'"
                                        :style="{ width: `${targetCoveragePct}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        <!-- Desglose Planta vs Camión y estado en revisión -->
                        <div class="mt-5 pt-4 border-t border-[#E7E4ED]">
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="p-3 rounded-xl bg-[#EEEAF7]/70 border border-[#1E1545]/10 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="h-7 w-7 rounded-lg bg-[#1E1545] text-white flex items-center justify-center shrink-0">
                                            <Store class="w-3.5 h-3.5" :stroke-width="2" />
                                        </span>
                                        <span class="text-xs font-medium text-[#777386] truncate">En planta</span>
                                    </div>
                                    <span class="text-sm font-bold text-[#211B35] tabular-nums shrink-0">{{ operations.inventory.plant_full_available }}</span>
                                </div>

                                <div class="p-3 rounded-xl bg-[#E5F4FD]/80 border border-[#0071BA]/15 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="h-7 w-7 rounded-lg bg-[#0071BA] text-white flex items-center justify-center shrink-0">
                                            <Truck class="w-3.5 h-3.5" :stroke-width="2" />
                                        </span>
                                        <span class="text-xs font-medium text-[#777386] truncate">En camión</span>
                                    </div>
                                    <span class="text-sm font-bold text-[#211B35] tabular-nums shrink-0">{{ operations.inventory.truck_full_available }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 5. TOP CLIENTES DEL MES + FLUJO OPERATIVO ACTUAL + ACCESOS DIRECTOS DE GESTIÓN -->
                <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

                    <!-- Top clientes del mes -->
                    <div class="lg:col-span-5 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col">
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="h-9 w-9 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shrink-0 shadow-sm">
                                    <Award class="w-4 h-4" :stroke-width="2" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">Top clientes del mes</h2>
                                    <p class="text-xs text-[#777386]">Mayor volumen entregado (20L)</p>
                                </div>
                            </div>
                            <Link
                                :href="route('admin.customers.index')"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#0071BA] hover:text-[#1E1545] transition shrink-0"
                            >
                                <span>Directorio</span>
                                <ArrowRight class="w-3.5 h-3.5" :stroke-width="2" />
                            </Link>
                        </div>

                        <div v-if="topCustomersMonth.length > 0" class="divide-y divide-[#E7E4ED] flex-1">
                            <Link
                                v-for="(cust, idx) in topCustomersMonth"
                                :key="cust.id"
                                :href="route('admin.customers.show', cust.id)"
                                class="py-2.5 first:pt-0 last:pb-0 flex items-center justify-between gap-3 hover:bg-[#F5F4F8] -mx-2 px-2.5 rounded-xl transition-colors"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <span
                                        class="h-7 w-7 rounded-lg text-xs font-bold flex items-center justify-center shrink-0 tabular-nums"
                                        :class="idx === 0
                                            ? 'bg-[#1E1545] text-white shadow-sm'
                                            : idx === 1
                                                ? 'bg-[#0071BA] text-white shadow-sm'
                                                : 'bg-[#EEEAF7] text-[#1E1545]'"
                                    >
                                        {{ idx + 1 }}
                                    </span>
                                    <p class="text-xs font-semibold text-[#211B35] truncate">
                                        {{ cust.name }}
                                        <span v-if="cust.nickname" class="text-[#777386] font-normal"> "{{ cust.nickname }}"</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2.5 shrink-0 tabular-nums">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#E5F4FD] text-[#0071BA]">
                                        {{ cust.total_bottles }} u.
                                    </span>
                                    <span class="text-xs font-bold text-[#211B35] min-w-[66px] text-right">
                                        {{ formatCurrency(cust.total_amount) }}
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="flex-1 flex flex-col items-center justify-center py-8 px-4 text-center rounded-2xl bg-[#EEEAF7]/45 border border-dashed border-[#1E1545]/20">
                            <p class="text-xs font-semibold text-[#211B35]">Sin consumo registrado este mes</p>
                            <p class="text-[11px] text-[#777386] mt-1">Los clientes destacados aparecerán cuando se completen entregas.</p>
                        </div>
                    </div>

                    <!-- Flujo operativo actual + Accesos directos -->
                    <div class="lg:col-span-7 bg-white rounded-2xl border border-[#E7E4ED] shadow-sm p-5 sm:p-6 flex flex-col justify-between gap-6">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3.5">
                                <div>
                                    <h2 class="text-base font-bold text-[#211B35] tracking-tight">Flujo operativo actual</h2>
                                    <p class="text-xs text-[#777386]">Distribución compacta de pedidos activos y entregas del día</p>
                                </div>
                                <span
                                    v-if="operations.pipeline.cancelled_today > 0"
                                    class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#FCECEF] text-[#E82147] border border-[#E82147]/25 tabular-nums"
                                >
                                    {{ operations.pipeline.cancelled_today }} cancelados hoy
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                                <div
                                    v-for="(stage, sIdx) in pipelineStages"
                                    :key="stage.key"
                                    class="p-3 rounded-xl border transition-colors"
                                    :class="[stage.bg, sIdx === pipelineStages.length - 1 ? 'col-span-2 sm:col-span-1' : '']"
                                >
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span class="text-[11px] font-semibold truncate" :class="stage.text">{{ stage.label }}</span>
                                        <span class="h-6 w-6 rounded-lg flex items-center justify-center shrink-0" :class="stage.chipBg">
                                            <component :is="stage.icon" class="w-3.5 h-3.5" :stroke-width="2.2" />
                                        </span>
                                    </div>
                                    <p class="text-xl font-bold text-[#211B35] mt-1.5 tabular-nums">{{ stage.value }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Accesos directos de gestión -->
                        <div class="pt-5 border-t border-[#E7E4ED]">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-[#777386]">
                                    Accesos directos de gestión
                                </h3>
                                <ArrowUpRight class="w-3.5 h-3.5 text-[#0071BA]" :stroke-width="2" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                <Link
                                    v-for="act in quickActions"
                                    :key="act.label"
                                    :href="act.href"
                                    class="group flex items-center justify-between gap-2.5 p-3 rounded-xl bg-[#F5F4F8]/80 hover:bg-white border border-[#E7E4ED] hover:shadow-sm transition-all"
                                    :class="act.hoverBorder"
                                >
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm transition-transform group-hover:scale-105"
                                            :class="act.iconClass"
                                        >
                                            <component :is="act.icon" class="w-4 h-4" :stroke-width="2" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-[#211B35] truncate">{{ act.label }}</p>
                                            <p class="text-[11px] text-[#777386] truncate">{{ act.sub }}</p>
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-[#777386] group-hover:text-[#1E1545] group-hover:translate-x-0.5 transition-all shrink-0" :stroke-width="2" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
