<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

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
        default: () => ({ pending_bottles: 0, pending_amount: 0, disclaimer: 'Son botellones pendientes de pago, no envases por devolver.' }),
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
    last_updated_at: {
        type: String,
        default: '',
    },
});

const reloadData = () => {
    router.reload({ preserveScroll: true });
};

const getStageBadgeClass = (stageKey) => {
    switch (stageKey) {
        case 'entregado':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'en_reparticion':
            return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'lavando':
        case 'listo_para_entregar':
        case 'llevando_a_planta':
            return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'asignado_a_ruta':
            return 'bg-cyan-50 text-cyan-800 border-cyan-200';
        case 'confirmado':
            return 'bg-indigo-50 text-indigo-800 border-indigo-200';
        case 'cancelado':
            return 'bg-rose-50 text-rose-800 border-rose-200';
        default:
            return 'bg-amber-50 text-amber-800 border-amber-200';
    }
};
</script>

<template>
    <Head title="Panel de Cliente - Puritronic" />

    <AuthenticatedLayout>
        <!-- 1. ENCABEZADO ÚNICO: Saludo breve y acciones de pedidos -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                        Hola, {{ customer?.nickname || $page.props.auth.user.name }}
                    </h1>
                    <p class="text-xs text-slate-500">
                        Resumen de tu cuenta y pedidos de agua purificada
                    </p>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap">
                    <!-- Botón de actualización de consulta -->
                    <button
                        @click="reloadData"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs transition active:scale-95"
                        title="Actualizar información"
                    >
                        <i class="fa-solid fa-arrows-rotate text-[11px] text-slate-400"></i>
                        <span>Actualizar</span>
                    </button>

                    <!-- Acción secundaria: Ver mis pedidos -->
                    <Link
                        :href="route('cliente.orders.index')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-regular fa-file-lines text-slate-500 text-xs"></i>
                        <span>Ver mis pedidos</span>
                    </Link>

                    <!-- Acción principal: Nuevo pedido (respeta bloqueo por pedido activo) -->
                    <Link
                        v-if="!activeOrder"
                        :href="route('cliente.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Nuevo pedido</span>
                    </Link>
                    <span
                        v-else
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-medium text-slate-400 bg-slate-100 border border-slate-200 cursor-not-allowed select-none"
                        title="Ya cuentas con un pedido activo en proceso. No puedes solicitar otro hasta que se complete o cancele."
                    >
                        <i class="fa-solid fa-lock text-xs"></i>
                        <span>Nuevo pedido</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Indicador de última actualización -->
                <div v-if="last_updated_at" class="flex justify-end text-[11px] text-slate-400">
                    <span>Consulta actualizada: {{ last_updated_at }}</span>
                </div>

                <!-- ALERTA DE ÉXITO FLASH -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200/90 text-emerald-900 shadow-xs flex items-start gap-3"
                >
                    <div class="h-6 w-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fa-solid fa-check text-xs"></i>
                    </div>
                    <div class="flex-1 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ $page.props.flash.success }}
                    </div>
                </div>

                <!-- ALERTA DE ERROR FLASH -->
                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200/90 text-rose-900 shadow-xs flex items-start gap-3"
                >
                    <div class="h-6 w-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                    </div>
                    <div class="flex-1 text-xs sm:text-sm leading-relaxed font-medium">
                        {{ $page.props.flash.error }}
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 2. SEGUIMIENTO DE PEDIDO ACTIVO (CON AVANCE OPERATIVO REAL)                -->
                <!-- ========================================================================= -->
                <div
                    v-if="activeOrder"
                    class="bg-white rounded-2xl border border-blue-200/80 p-5 sm:p-6 shadow-xs space-y-4"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div class="flex items-start sm:items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-truck-fast text-base"></i>
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-slate-500 font-medium">Pedido en curso:</span>
                                    <span class="font-mono font-bold text-sm text-slate-900">{{ activeOrder.folio }}</span>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                        :class="getStageBadgeClass(activeOrder.operational_progress?.overall_stage_key)"
                                    >
                                        {{ activeOrder.operational_progress?.overall_stage_label || activeOrder.status_label }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600">
                                    {{ activeOrder.operational_progress?.explanation || `${activeOrder.total_bottles} botellones en proceso.` }}
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('cliente.orders.show', activeOrder.id)"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl transition shrink-0 cursor-pointer"
                        >
                            <span>Ver seguimiento</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </Link>
                    </div>

                    <!-- Desglose de Modalidad del Pedido -->
                    <div v-if="activeOrder.operational_progress" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <!-- Tarjeta de Cambio -->
                        <div
                            v-if="activeOrder.operational_progress.exchange"
                            class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/70 text-xs space-y-1.5"
                        >
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-blue-950 flex items-center gap-1.5">
                                    <i class="fa-solid fa-arrows-rotate text-blue-900"></i> Cambio (Llenos por vacíos)
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-md text-[11px] border"
                                    :class="getStageBadgeClass(activeOrder.operational_progress.exchange.stage_key)"
                                >
                                    {{ activeOrder.operational_progress.exchange.stage_label }}
                                </span>
                            </div>
                            <div class="flex justify-between text-slate-600 text-[11px]">
                                <span>Solicitados: {{ activeOrder.operational_progress.exchange.quantity_requested }}</span>
                                <span>Entregados: {{ activeOrder.operational_progress.exchange.delivered_quantity }}</span>
                                <span v-if="activeOrder.operational_progress.exchange.cancelled_quantity > 0" class="text-rose-600 font-bold">
                                    Cancelados: {{ activeOrder.operational_progress.exchange.cancelled_quantity }}
                                </span>
                            </div>
                            <p v-if="activeOrder.operational_progress.exchange.cancellation_reason" class="text-[11px] text-rose-700 bg-rose-50 p-1.5 rounded-lg border border-rose-100">
                                Motivo: {{ activeOrder.operational_progress.exchange.cancellation_reason }}
                            </p>
                        </div>

                        <!-- Tarjeta de Fijos -->
                        <div
                            v-if="activeOrder.operational_progress.fixed"
                            class="p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/70 text-xs space-y-1.5"
                        >
                            <div class="flex items-center justify-between font-bold">
                                <span class="text-purple-950 flex items-center gap-1.5">
                                    <i class="fa-solid fa-tag text-purple-900"></i> Fijos (Tus envases)
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-md text-[11px] border"
                                    :class="getStageBadgeClass(activeOrder.operational_progress.fixed.stage_key)"
                                >
                                    {{ activeOrder.operational_progress.fixed.stage_label }}
                                </span>
                            </div>
                            <div class="flex justify-between text-slate-600 text-[11px]">
                                <span>Solicitados: {{ activeOrder.operational_progress.fixed.quantity_requested }}</span>
                                <span>Recogidos: {{ activeOrder.operational_progress.fixed.collected_quantity }}</span>
                                <span>Devueltos: {{ activeOrder.operational_progress.fixed.delivered_quantity }}</span>
                            </div>
                            <p v-if="activeOrder.operational_progress.fixed.cancelled_quantity > 0" class="text-[11px] text-rose-700 bg-rose-50 p-1.5 rounded-lg border border-rose-100">
                                Unidades no entregadas/canceladas: {{ activeOrder.operational_progress.fixed.cancelled_quantity }} ({{ activeOrder.operational_progress.fixed.cancellation_reason }})
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 3. SALDO Y PROMOCIÓN DEL CLIENTE                                          -->
                <!-- ========================================================================= -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6 items-start">

                    <!-- TARJETA: SALDO Y PENDIENTES DE PAGO -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-lg bg-amber-50 text-amber-800 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-receipt text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">
                                        Estado de cuenta
                                    </h2>
                                    <p class="text-[11px] text-slate-500">
                                        Pendientes de pago e historial
                                    </p>
                                </div>
                            </div>

                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                :class="debt.pending_bottles > 0 ? 'bg-amber-50 text-amber-900 border-amber-300' : 'bg-emerald-50 text-emerald-900 border-emerald-300'"
                            >
                                {{ debt.pending_bottles > 0 ? `${debt.pending_bottles} botellones pendientes` : 'Al día' }}
                            </span>
                        </div>

                        <!-- Métricas de deuda -->
                        <div class="grid grid-cols-2 gap-3 py-1">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-center">
                                <span class="text-[11px] font-medium text-slate-500 block">Botellones pendientes</span>
                                <span class="text-2xl font-black text-slate-900 font-mono mt-0.5 block">
                                    {{ debt.pending_bottles }}
                                </span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-center">
                                <span class="text-[11px] font-medium text-slate-500 block">Importe pendiente</span>
                                <span class="text-2xl font-black text-slate-900 font-mono mt-0.5 block">
                                    ${{ debt.pending_amount }}
                                </span>
                            </div>
                        </div>

                        <!-- Aclaración requerida -->
                        <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200/70 text-amber-900 text-xs flex items-start gap-2">
                            <i class="fa-solid fa-circle-info text-amber-700 mt-0.5 text-xs shrink-0"></i>
                            <span class="text-[11px] leading-relaxed">
                                {{ debt.disclaimer }}
                            </span>
                        </div>

                        <!-- Historial desplegable de pagos y entregas gratuitas -->
                        <details class="group pt-2 border-t border-slate-100 text-xs">
                            <summary class="cursor-pointer font-bold text-slate-600 hover:text-slate-900 flex items-center justify-between list-none select-none py-1">
                                <span class="flex items-center gap-1.5">
                                    <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                                    <span>Ver últimos pagos y entregas gratuitas</span>
                                </span>
                                <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 group-open:rotate-180 transition-transform"></i>
                            </summary>

                            <div class="mt-3 space-y-2.5">
                                <!-- Pagos en efectivo -->
                                <div>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Últimos pagos registrados</span>
                                    <div v-if="payment_history.length > 0" class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
                                        <div
                                            v-for="p in payment_history"
                                            :key="p.id"
                                            class="p-2.5 bg-slate-50/50 flex items-center justify-between text-xs"
                                        >
                                            <div>
                                                <span class="font-bold text-slate-800">${{ p.amount }} pagados</span>
                                                <span class="text-slate-400 block text-[10px]">{{ p.bottles_count }} botellón(es) liquidado(s) · {{ p.date }}</span>
                                            </div>
                                            <span v-if="p.order_folio" class="text-[10px] font-mono text-slate-500 bg-white px-1.5 py-0.5 rounded border border-slate-200">
                                                {{ p.order_folio }}
                                            </span>
                                        </div>
                                    </div>
                                    <p v-else class="text-[11px] text-slate-400 italic">No hay pagos recientes registrados.</p>
                                </div>

                                <!-- Entregas gratuitas -->
                                <div class="pt-2 border-t border-slate-100">
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Entregas gratuitas recibidas</span>
                                    <div v-if="free_deliveries.length > 0" class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
                                        <div
                                            v-for="f in free_deliveries"
                                            :key="f.id"
                                            class="p-2.5 bg-emerald-50/40 flex items-center justify-between text-xs text-emerald-950"
                                        >
                                            <div>
                                                <span class="font-bold text-emerald-800 flex items-center gap-1.5">
                                                    <i class="fa-solid fa-gift text-emerald-600"></i>
                                                    <span>1 botellón gratuito bonificado</span>
                                                </span>
                                                <span class="text-emerald-700 block text-[10px]">{{ f.date }}</span>
                                            </div>
                                            <span v-if="f.order_folio" class="text-[10px] font-mono text-emerald-800 bg-white px-1.5 py-0.5 rounded border border-emerald-200">
                                                {{ f.order_folio }}
                                            </span>
                                        </div>
                                    </div>
                                    <p v-else class="text-[11px] text-slate-400 italic">No hay promociones canjeadas registradas.</p>
                                </div>
                            </div>
                        </details>
                    </div>

                    <!-- TARJETA: PROMOCIÓN SEMANAL (9+1) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-gift text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900">
                                        Promoción Semanal (9 + 1)
                                    </h2>
                                    <p class="text-[11px] text-slate-500">
                                        Por cada 9 botellones pagados, el 10.º es gratis
                                    </p>
                                </div>
                            </div>

                            <span
                                v-if="weekly_promo"
                                class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                :class="weekly_promo.can_redeem ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : (weekly_promo.status === 'redeemed' ? 'bg-blue-50 text-blue-800 border-blue-300' : 'bg-slate-100 text-slate-700 border-slate-200')"
                            >
                                {{ weekly_promo.status_label }}
                            </span>
                        </div>

                        <!-- Barra y avance de botellones pagados -->
                        <div v-if="weekly_promo" class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-800">
                                <span>Progreso de la semana:</span>
                                <span class="font-mono text-blue-900">{{ weekly_promo.paid_count }} de {{ weekly_promo.required }} pagados</span>
                            </div>

                            <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden p-0.5 border border-slate-200">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="weekly_promo.paid_count >= weekly_promo.required ? 'bg-emerald-500' : 'bg-blue-600'"
                                    :style="{ width: `${Math.min(100, Math.round((weekly_promo.paid_count / weekly_promo.required) * 100))}%` }"
                                ></div>
                            </div>

                            <p class="text-xs text-slate-600 pt-1 leading-relaxed">
                                {{ weekly_promo.label }}
                            </p>

                            <!-- Fecha límite de beneficio disponible -->
                            <div
                                v-if="weekly_promo.can_redeem"
                                class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs space-y-1"
                            >
                                <span class="font-bold flex items-center gap-1.5 text-emerald-900">
                                    <i class="fa-solid fa-gift text-emerald-600"></i> ¡Tienes 1 botellón de 20L gratuito disponible!
                                </span>
                                <p class="text-[11px] text-emerald-800">
                                    Válido hasta el <strong>{{ weekly_promo.expires_at_label }}</strong> (horario de Hocabá, Yucatán). Recuerda tener listo tu envase vacío para el intercambio.
                                </p>
                            </div>

                            <div
                                v-else-if="weekly_promo.status === 'expired'"
                                class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs"
                            >
                                El beneficio no utilizado venció al concluir el ciclo semanal.
                            </div>
                        </div>

                        <div class="p-2.5 rounded-xl bg-slate-50 text-[11px] text-slate-500 leading-relaxed border border-slate-100">
                            * Se contabilizan bajo la zona horaria oficial de Mérida. Los botellones a crédito suman al ser liquidados en efectivo.
                        </div>
                    </div>

                </div>

                <!-- 4. CONTROL: BOTELLONES Y DOMICILIO -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 sm:gap-6 items-start">

                    <!-- TARJETA UNIFICADA: BOTELLONES REGISTRADOS (20 LITROS) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-bottle-water text-sm"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900">
                                            Botellones (20 litros)
                                        </h2>
                                        <p class="text-[11px] text-slate-500">
                                            Inventario registrado en tu cuenta
                                        </p>
                                    </div>
                                </div>

                                <Link
                                    :href="route('profile.edit')"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                    title="Modificar tus cantidades de botellones"
                                >
                                    <i class="fa-solid fa-pen-to-square text-[11px] text-slate-500"></i>
                                    <span>Ajustar</span>
                                </Link>
                            </div>

                            <!-- Métricas de botellones -->
                            <div class="grid grid-cols-3 gap-2.5 py-4">
                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                                    <span class="text-[11px] font-medium text-slate-500 block">Fijos</span>
                                    <span class="text-2xl font-semibold text-slate-900 font-mono mt-0.5 block">
                                        {{ customer?.fixed_bottles_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-100 text-center">
                                    <span class="text-[11px] font-medium text-slate-500 block">De cambio</span>
                                    <span class="text-2xl font-semibold text-slate-900 font-mono mt-0.5 block">
                                        {{ customer?.exchange_bottles_count ?? 0 }}
                                    </span>
                                </div>

                                <div class="p-3 rounded-xl bg-blue-50/50 border border-blue-100 text-center">
                                    <span class="text-[11px] font-medium text-blue-900 block">Total</span>
                                    <span class="text-2xl font-semibold text-blue-950 font-mono mt-0.5 block">
                                        {{ customer?.total_bottles ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TARJETA: DOMICILIO DE ENTREGA -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-location-dot text-sm"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-semibold text-slate-900">
                                            Domicilio de entrega
                                        </h2>
                                        <p class="text-[11px] text-slate-500">
                                            Dirección para tus pedidos en Hocabá
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="customer?.zone_label"
                                    class="text-[11px] font-medium px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    {{ customer.zone_label }}
                                </span>
                            </div>

                            <!-- Información de la dirección -->
                            <div class="py-4 space-y-2">
                                <template v-if="customer?.has_address">
                                    <div class="space-y-0.5">
                                        <span class="text-[11px] font-medium text-slate-500 block">Dirección</span>
                                        <p class="text-sm font-semibold text-slate-900 leading-snug">
                                            {{ customer.address_summary }}
                                        </p>
                                    </div>

                                    <div v-if="customer?.between_streets" class="text-xs text-slate-600 pt-1">
                                        <span class="text-slate-400 text-[11px]">Cruzamientos:</span>
                                        <span class="ml-1 text-slate-700 font-medium">{{ customer.between_streets }}</span>
                                    </div>

                                    <div v-if="customer?.reference" class="text-xs text-slate-600">
                                        <span class="text-slate-400 text-[11px]">Referencia:</span>
                                        <span class="ml-1 text-slate-700 font-medium">{{ customer.reference }}</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="py-3 text-center space-y-1">
                                        <p class="text-xs font-medium text-slate-700">
                                            Aún no has registrado tu domicilio de entrega.
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Enlace para administrarlo -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">
                                Hocabá, Yucatán
                            </span>
                            <Link
                                :href="route('cliente.addresses.index')"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-900 hover:text-blue-800 transition cursor-pointer"
                            >
                                <span>{{ customer?.has_address ? 'Administrar domicilio' : 'Registrar domicilio' }}</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </Link>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
