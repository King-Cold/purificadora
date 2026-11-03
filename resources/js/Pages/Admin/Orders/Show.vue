<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    last_updated_at: {
        type: String,
        default: null,
    },
});

const isReloading = ref(false);
const reloadData = () => {
    isReloading.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isReloading.value = false;
        },
    });
};

// Modal de Confirmación
const showConfirmModal = ref(false);
const confirmForm = useForm({
    comment: '',
});

const openConfirmModal = () => {
    confirmForm.clearErrors();
    confirmForm.comment = '';
    showConfirmModal.value = true;
};

const closeConfirmModal = () => {
    if (confirmForm.processing) return;
    showConfirmModal.value = false;
};

const submitConfirmation = () => {
    confirmForm.post(route('admin.orders.confirm', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmModal.value = false;
        },
    });
};

// Modal de Cancelación
const showCancelModal = ref(false);
const cancelForm = useForm({
    reason: '',
});

const openCancelModal = () => {
    cancelForm.clearErrors();
    cancelForm.reason = '';
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    if (cancelForm.processing) return;
    showCancelModal.value = false;
};

const submitCancellation = () => {
    cancelForm.post(route('admin.orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};

const getStageBadgeClass = (stageKey) => {
    switch (stageKey) {
        case 'entregado':
            return 'bg-emerald-50 text-emerald-800 border-emerald-300';
        case 'en_reparticion':
            return 'bg-indigo-50 text-indigo-900 border-indigo-300';
        case 'asignado_a_ruta':
            return 'bg-purple-50 text-purple-900 border-purple-300';
        case 'llevando_a_planta':
        case 'lavando':
        case 'listo_para_entregar':
            return 'bg-sky-50 text-sky-900 border-sky-300';
        case 'confirmed':
        case 'confirmado':
            return 'bg-blue-50 text-blue-900 border-blue-300';
        case 'cancelled':
        case 'cancelado':
            return 'bg-rose-50 text-rose-800 border-rose-300';
        case 'pending':
        case 'pendiente':
        default:
            return 'bg-amber-50 text-amber-900 border-amber-300';
    }
};

const getStageIconClass = (stageKey) => {
    switch (stageKey) {
        case 'entregado':
            return 'fa-solid fa-check-double text-emerald-600';
        case 'en_reparticion':
            return 'fa-solid fa-truck-fast text-indigo-600';
        case 'asignado_a_ruta':
            return 'fa-solid fa-route text-purple-600';
        case 'llevando_a_planta':
            return 'fa-solid fa-truck-arrow-right text-sky-600';
        case 'lavando':
            return 'fa-solid fa-soap text-sky-600';
        case 'listo_para_entregar':
            return 'fa-solid fa-boxes-packing text-sky-600';
        case 'confirmed':
        case 'confirmado':
            return 'fa-solid fa-circle-check text-blue-700';
        case 'cancelled':
        case 'cancelado':
            return 'fa-solid fa-ban text-rose-600';
        case 'pending':
        case 'pendiente':
        default:
            return 'fa-regular fa-clock text-amber-600';
    }
};
</script>

<template>
    <Head :title="`Supervisión Pedido ${order.folio} - Puritronic`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <Link
                        :href="route('admin.orders.index')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0 cursor-pointer"
                        title="Volver al listado de pedidos"
                    >
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </Link>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 font-mono">
                                Pedido {{ order.folio }}
                            </h2>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border',
                                    getStageBadgeClass(order.operational_progress?.overall_stage_key || order.status)
                                ]"
                            >
                                <i :class="getStageIconClass(order.operational_progress?.overall_stage_key || order.status)" class="text-[11px]"></i>
                                <span>{{ order.operational_progress?.overall_stage_label || order.status_label }}</span>
                            </span>
                            <span
                                v-if="order.has_collected_fixed_custody"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300"
                                title="Hay botellones fijos bajo custodia de la empresa"
                            >
                                <i class="fa-solid fa-shield-halved text-amber-700"></i>
                                <span>Fijos en Custodia</span>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Registrado el {{ order.created_at }} &bull; Botellones de 20 Litros
                        </p>
                    </div>
                </div>

                <!-- Botones de Acción Administrativa y Recarga -->
                <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
                    <button
                        type="button"
                        @click="reloadData"
                        :disabled="isReloading"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 transition shadow-2xs cursor-pointer"
                        title="Recalcular supervisión operativa"
                    >
                        <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin text-blue-900': isReloading }"></i>
                        <span>{{ isReloading ? 'Actualizando...' : 'Actualizar' }}</span>
                    </button>


                    <!-- Botón Cancelar Ordinario (Bloqueado si hay fijos en custodia por regla de negocio) -->
                    <button
                        v-if="order.can_cancel && (order.is_pending || order.is_confirmed) && !order.has_collected_fixed_custody"
                        type="button"
                        @click="openCancelModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-solid fa-ban"></i>
                        <span>Cancelar Pedido</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. ALERTAS FLASH -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-emerald-950">Operación Exitosa</h4>
                        <p class="text-xs sm:text-sm text-emerald-800 mt-0.5 leading-relaxed font-medium">
                            {{ $page.props.flash.success }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-rose-950">Atención</h4>
                        <p class="text-xs sm:text-sm text-rose-800 mt-0.5 leading-relaxed font-medium">
                            {{ $page.props.flash.error }}
                        </p>
                    </div>
                </div>

                <!-- ALERTA DE CUSTODIA BLOQUEANTE DE CANCELACIÓN -->
                <div
                    v-if="order.has_collected_fixed_custody"
                    class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-shield-halved text-lg"></i>
                    </div>
                    <div class="flex-1 space-y-1">
                        <h4 class="text-sm font-bold text-amber-950">Envases Fijos Bajo Custodia Activa</h4>
                        <p class="text-xs sm:text-sm text-amber-900 leading-relaxed">
                            Este pedido cuenta con botellones de propiedad del cliente recolectados por reparto. Por integridad física y contable, <strong>no se permite la cancelación ordinaria</strong> mientras existan botellones fijos bajo custodia de la purificadora sin haber sido devueltos formalmente al propietario.
                        </p>
                        <p class="text-xs text-amber-800">
                            Cualquier discrepancia o siniestro físico debe gestionarse como incidencia de custodia para preservar la trazabilidad.
                        </p>
                    </div>
                </div>

                <!-- 2. AVISO DE CANCELACIÓN (SI EL PEDIDO ESTÁ CANCELADO) -->
                <div
                    v-if="order.is_cancelled"
                    class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-ban text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-rose-950">Este pedido se encuentra cancelado</h4>
                        <p v-if="order.cancellation_reason" class="text-xs sm:text-sm text-rose-800 mt-1 leading-relaxed">
                            <span class="font-bold">Motivo registrado:</span> "{{ order.cancellation_reason }}"
                        </p>
                        <p class="text-xs text-rose-700 mt-1">
                            Los pedidos cancelados son inmutables y liberan el límite transaccional de pedido activo.
                        </p>
                    </div>
                </div>

                <!-- 3. SEGUIMIENTO OPERATIVO GRANULAR (DESGLOSE COMPLETO) -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                            <i class="fa-solid fa-diagram-project text-blue-900"></i>
                            <span>Progreso Operativo Granular</span>
                        </h3>
                        <span v-if="last_updated_at" class="text-[11px] text-slate-400 font-mono">
                            Calculado al: {{ last_updated_at }}
                        </span>
                    </div>

                    <div v-if="order.operational_progress?.explanation" class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-800 leading-relaxed">
                        <span class="font-bold text-blue-950">Estado actual:</span> {{ order.operational_progress.explanation }}
                    </div>

                    <!-- Desglose independiente de modalidades -->
                    <div v-if="order.operational_progress" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Modalidad Cambio -->
                        <div v-if="order.operational_progress.exchange" class="p-4 rounded-xl border border-sky-200 bg-sky-50/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-sky-900">Botellones de Cambio</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-sky-900 border border-sky-300">
                                    {{ order.operational_progress.exchange.stage_label }}
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="bg-white p-2 rounded-lg border border-sky-100">
                                    <span class="text-[10px] text-slate-400 block">Solicitados</span>
                                    <span class="font-mono font-bold text-slate-900">{{ order.operational_progress.exchange.quantity_requested }}</span>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-sky-100">
                                    <span class="text-[10px] text-emerald-600 block">Entregados</span>
                                    <span class="font-mono font-bold text-emerald-700">{{ order.operational_progress.exchange.delivered_quantity }}</span>
                                </div>
                                <div class="bg-white p-2 rounded-lg border border-sky-100">
                                    <span class="text-[10px] text-rose-500 block">Cancelados</span>
                                    <span class="font-mono font-bold text-rose-700">{{ order.operational_progress.exchange.cancelled_quantity }}</span>
                                </div>
                            </div>
                            <p v-if="order.operational_progress.exchange.cancellation_reason" class="text-xs text-rose-800 pt-1">
                                <span class="font-semibold">Motivo cancelación:</span> {{ order.operational_progress.exchange.cancellation_reason }}
                            </p>
                        </div>

                        <!-- Modalidad Fijos -->
                        <div v-if="order.operational_progress.fixed" class="p-4 rounded-xl border border-blue-200 bg-blue-50/30 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-blue-950">Botellones Fijos (Identificados)</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-blue-900 border border-blue-300">
                                    {{ order.operational_progress.fixed.stage_label }}
                                </span>
                            </div>
                            <div class="grid grid-cols-4 gap-1 text-center text-xs">
                                <div class="bg-white p-1.5 rounded-lg border border-blue-100">
                                    <span class="text-[9px] text-slate-400 block">Solicitados</span>
                                    <span class="font-mono font-bold text-slate-900">{{ order.operational_progress.fixed.quantity_requested }}</span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-blue-100">
                                    <span class="text-[9px] text-sky-600 block">Recolectados</span>
                                    <span class="font-mono font-bold text-sky-800">{{ order.operational_progress.fixed.collected_quantity }}</span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-blue-100">
                                    <span class="text-[9px] text-emerald-600 block">Devueltos</span>
                                    <span class="font-mono font-bold text-emerald-700">{{ order.operational_progress.fixed.delivered_quantity }}</span>
                                </div>
                                <div class="bg-white p-1.5 rounded-lg border border-blue-100">
                                    <span class="text-[9px] text-rose-500 block">Cancelados</span>
                                    <span class="font-mono font-bold text-rose-700">{{ order.operational_progress.fixed.cancelled_quantity }}</span>
                                </div>
                            </div>
                            <p v-if="order.operational_progress.fixed.cancellation_reason" class="text-xs text-rose-800 pt-1">
                                <span class="font-semibold">Motivo:</span> {{ order.operational_progress.fixed.cancellation_reason }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 4. CUADRÍCULA DE DETALLES PRINCIPALES -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- COLUMNA IZQUIERDA: CLIENTE Y REGISTRO (1 COL) -->
                    <div class="space-y-6">

                        <!-- TARJETA: INFORMACIÓN DEL CLIENTE -->
                        <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Cliente Solicitante
                                </h3>
                                <Link
                                    v-if="order.customer"
                                    :href="route('admin.customers.show', order.customer.id)"
                                    class="text-xs font-semibold text-blue-900 hover:text-blue-700 transition"
                                >
                                    Ver perfil <i class="fa-solid fa-arrow-up-right-from-square text-[10px] ml-0.5"></i>
                                </Link>
                            </div>

                            <div v-if="order.customer" class="space-y-3">
                                <div>
                                    <h4 class="text-base font-bold text-slate-900">{{ order.customer.name }}</h4>
                                    <p v-if="order.customer.nickname" class="text-xs text-slate-600 font-medium">
                                        "{{ order.customer.nickname }}"
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ order.customer.code }}
                                    </span>
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-lg text-xs font-bold border',
                                            order.customer.has_account
                                                ? 'bg-blue-50 text-blue-900 border-blue-200'
                                                : 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                        ]"
                                    >
                                        {{ order.customer.has_account ? 'Con Cuenta' : 'Sin Cuenta' }}
                                    </span>
                                </div>

                                <div v-if="order.customer.phone" class="text-xs text-slate-600 flex items-center gap-2 pt-1">
                                    <i class="fa-solid fa-phone text-slate-400 text-xs"></i>
                                    <span>{{ order.customer.phone }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: USUARIO QUE REGISTRÓ EL PEDIDO -->
                        <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 pb-3 border-b border-slate-100">
                                Registro del Pedido
                            </h3>

                            <div v-if="order.creator" class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm shrink-0">
                                    <i class="fa-solid fa-user-shield text-slate-500"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Registrado por
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 truncate">
                                        {{ order.creator.name }}
                                    </div>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ order.creator.role }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500">
                                Registrado directamente por el cliente desde el portal web.
                            </div>
                        </div>

                    </div>

                    <!-- COLUMNA CENTRAL Y DERECHA: BOTELLONES Y DOMICILIO (2 COLS) -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- TARJETA: BOTELLONES SOLICITADOS -->
                        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Botellones de 20 Litros Solicitados
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900">
                                    Capacidad 20L
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200/80 text-center">
                                    <span class="text-xs font-bold text-blue-900 uppercase tracking-wider block">Fijos</span>
                                    <span class="text-2xl font-black text-blue-950 mt-1 block">{{ order.fixed_quantity }}</span>
                                    <span class="text-[11px] text-blue-800">Botellones 20L</span>
                                </div>

                                <div class="p-4 rounded-xl bg-sky-50/70 border border-sky-200/80 text-center">
                                    <span class="text-xs font-bold text-sky-900 uppercase tracking-wider block">De Cambio</span>
                                    <span class="text-2xl font-black text-sky-950 mt-1 block">{{ order.exchange_quantity }}</span>
                                    <span class="text-[11px] text-sky-800">Botellones 20L</span>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-100/80 border border-slate-200 text-center">
                                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Total Solicitado</span>
                                    <span class="text-2xl font-black text-slate-900 mt-1 block">{{ order.total_bottles }}</span>
                                    <span class="text-[11px] text-slate-600">Botellones de 20L</span>
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div v-if="order.notes" class="pt-3 border-t border-slate-100">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">
                                    Observaciones de entrega:
                                </span>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-700 italic">
                                    "{{ order.notes }}"
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: CONTACTO Y DOMICILIO HISTÓRICO -->
                        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Domicilio y Contacto Histórico del Pedido
                                </h3>
                                <span class="text-[11px] text-slate-400 font-medium">
                                    Instantánea inmutable del pedido
                                </span>
                            </div>

                            <div class="space-y-3 text-xs sm:text-sm">
                                <div class="flex items-start gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900">
                                            {{ order.address?.full_address || 'Sin dirección registrada' }}
                                        </div>
                                        <div v-if="order.address?.zone_label" class="mt-1">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-900">
                                                Zona {{ order.address.zone_label }}
                                            </span>
                                        </div>
                                        <div v-if="order.address?.reference" class="text-xs text-slate-500 mt-1">
                                            <span class="font-semibold text-slate-700">Referencia:</span> {{ order.address.reference }}
                                        </div>
                                        <div v-if="order.address?.between_streets" class="text-xs text-slate-500">
                                            <span class="font-semibold text-slate-700">Entre calles:</span> {{ order.address.between_streets }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="order.address?.recipient_name || order.address?.recipient_phone" class="pt-2 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-600">
                                    <div v-if="order.address?.recipient_name">
                                        <span class="text-slate-400">Receptor:</span> <span class="font-semibold text-slate-800">{{ order.address.recipient_name }}</span>
                                    </div>
                                    <div v-if="order.address?.recipient_phone">
                                        <span class="text-slate-400">Teléfono contacto:</span> <span class="font-semibold text-slate-800">{{ order.address.recipient_phone }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- 5. PARADAS DE RUTA ASOCIADAS Y VISITAS -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 pb-3 border-b border-slate-100 flex items-center gap-2">
                        <i class="fa-solid fa-route text-blue-900"></i>
                        <span>Paradas de Ruta y Visitas de Reparto</span>
                    </h3>

                    <div v-if="order.route_stops?.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Ruta</th>
                                    <th class="py-2.5 px-3">Repartidor</th>
                                    <th class="py-2.5 px-3 text-center">Parada #</th>
                                    <th class="py-2.5 px-3">Estado</th>
                                    <th class="py-2.5 px-3">Atendida el</th>
                                    <th class="py-2.5 px-3">Motivo / Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="stop in order.route_stops" :key="stop.id">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-900">{{ stop.route_folio || '-' }}</td>
                                    <td class="py-3 px-3 font-medium text-slate-700">{{ stop.driver_name }}</td>
                                    <td class="py-3 px-3 text-center font-mono font-bold">{{ stop.stop_number }}</td>
                                    <td class="py-3 px-3">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                                stop.status === 'completed' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' :
                                                stop.status === 'failed' ? 'bg-rose-50 text-rose-800 border-rose-300' :
                                                'bg-amber-50 text-amber-900 border-amber-300'
                                            ]"
                                        >
                                            {{ stop.status_label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-mono text-slate-600">{{ stop.completed_at || '-' }}</td>
                                    <td class="py-3 px-3 text-slate-600">
                                        <span v-if="stop.failure_reason" class="text-rose-700 font-medium block">
                                            Fallo: {{ stop.failure_reason }}
                                        </span>
                                        <span v-if="stop.notes" class="text-slate-500 italic block">
                                            {{ stop.notes }}
                                        </span>
                                        <span v-if="!stop.failure_reason && !stop.notes">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-xs text-slate-400 italic py-2">
                        Este pedido no ha sido asignado a ninguna hoja de ruta aún.
                    </div>
                </div>

                <!-- 6. MOVIMIENTOS AUDITABLES DE CUSTODIA (BOTTLE CUSTODY MOVEMENTS) -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                            <i class="fa-solid fa-boxes-stacked text-blue-900"></i>
                            <span>Trazabilidad de Custodia Física de Envases</span>
                        </h3>
                        <span class="text-[11px] text-slate-400">
                            Cargas, recolecciones y entregas auditables
                        </span>
                    </div>

                    <div v-if="order.custody_movements?.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Fecha</th>
                                    <th class="py-2.5 px-3">Movimiento</th>
                                    <th class="py-2.5 px-3">Modalidad / Condición</th>
                                    <th class="py-2.5 px-3 text-center">Cant.</th>
                                    <th class="py-2.5 px-3">Origen &rarr; Destino</th>
                                    <th class="py-2.5 px-3">Responsable</th>
                                    <th class="py-2.5 px-3">Confirmado por</th>
                                    <th class="py-2.5 px-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="m in order.custody_movements" :key="m.id">
                                    <td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">{{ m.created_at }}</td>
                                    <td class="py-2.5 px-3 font-semibold text-slate-900 capitalize">{{ m.movement_type.replace('_', ' ') }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="capitalize font-medium text-slate-700">{{ m.bottle_type }}</span>
                                        <span class="text-slate-400 ml-1 font-mono">({{ m.bottle_condition }})</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono font-bold text-blue-950">{{ m.quantity }}</td>
                                    <td class="py-2.5 px-3 text-slate-600 font-mono text-[11px]">{{ m.origin }} &rarr; {{ m.destination }}</td>
                                    <td class="py-2.5 px-3 text-slate-700 font-medium">{{ m.user_name }}</td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ m.confirmed_by_name || '-' }}</td>
                                    <td class="py-2.5 px-3">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold border',
                                                m.status === 'accepted' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' :
                                                m.status === 'proposed' ? 'bg-amber-50 text-amber-900 border-amber-300' :
                                                'bg-slate-100 text-slate-700 border-slate-300'
                                            ]"
                                        >
                                            {{ m.status === 'accepted' ? 'Aceptado' : m.status === 'proposed' ? 'Propuesto' : m.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-xs text-slate-400 italic py-2">
                        No hay movimientos físicos de custodia registrados para este pedido.
                    </div>
                </div>

                <!-- 7. CARGOS Y PAGOS DEL PEDIDO (CON PRECIOS HISTÓRICOS Y AUDITORÍA) -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                            <i class="fa-solid fa-receipt text-blue-900"></i>
                            <span>Cargos Generados y Pagos Recibidos</span>
                        </h3>
                        <span class="text-[11px] text-slate-400">
                            Precios históricos inmutables
                        </span>
                    </div>

                    <!-- Tabla de Cargos -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Cargos por entrega</h4>
                        <div v-if="order.charges?.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Modalidad</th>
                                        <th class="py-2 px-3 text-center">Cant. Entregada</th>
                                        <th class="py-2 px-3 text-right">Precio Unitario</th>
                                        <th class="py-2 px-3 text-right">Importe Total</th>
                                        <th class="py-2 px-3 text-right">Importe Pagado</th>
                                        <th class="py-2 px-3 text-center">Estado de Cobro</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="c in order.charges" :key="c.id">
                                        <td class="py-2.5 px-3 font-semibold text-slate-900 capitalize">{{ c.bottle_type }} (20L)</td>
                                        <td class="py-2.5 px-3 text-center font-mono font-bold">{{ c.quantity_delivered }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono">${{ Number(c.unit_price).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">${{ Number(c.total_amount).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono text-emerald-700 font-bold">${{ Number(c.paid_amount).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-center">
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                                    c.status === 'paid' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' :
                                                    c.status === 'waived_promotion' ? 'bg-purple-50 text-purple-900 border-purple-300' :
                                                    'bg-amber-50 text-amber-900 border-amber-300'
                                                ]"
                                            >
                                                {{ c.status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-xs text-slate-400 italic py-1">Sin cargos registrados aún.</div>
                    </div>

                    <!-- Tabla de Pagos -->
                    <div class="space-y-2 pt-3 border-t border-slate-100">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Pagos en efectivo liquidados</h4>
                        <div v-if="order.payments?.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 uppercase font-bold border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 px-3">Fecha</th>
                                        <th class="py-2 px-3 text-right">Monto Liquidado</th>
                                        <th class="py-2 px-3 text-right">Efectivo Recibido</th>
                                        <th class="py-2 px-3 text-right">Cambio Devuelto</th>
                                        <th class="py-2 px-3 text-center">Botellones Cubiertos</th>
                                        <th class="py-2 px-3">Cobrado por</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="p in order.payments" :key="p.id">
                                        <td class="py-2.5 px-3 font-mono text-slate-500">{{ p.date }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">${{ Number(p.amount).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-700">${{ Number(p.cash_tendered).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-500">${{ Number(p.change_returned).toFixed(2) }}</td>
                                        <td class="py-2.5 px-3 text-center font-mono font-bold">{{ p.bottles_count }} u.</td>
                                        <td class="py-2.5 px-3 text-slate-800 font-medium">{{ p.received_by }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-xs text-slate-400 italic py-1">Sin pagos en efectivo registrados aún.</div>
                    </div>
                </div>

                <!-- 8. TARJETA: HISTORIAL CRONOLÓGICO DE ESTADOS -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 pb-3 border-b border-slate-100">
                        Historial de Estados y Responsables
                    </h3>

                    <div v-if="order.history && order.history.length > 0" class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                        <div
                            v-for="(hist, idx) in order.history"
                            :key="hist.id || idx"
                            class="relative text-xs sm:text-sm"
                        >
                            <!-- Punto en la línea de tiempo -->
                            <span class="absolute -left-6 top-1 h-5 w-5 rounded-full bg-white border-2 border-blue-900 flex items-center justify-center">
                                <span class="h-2 w-2 rounded-full bg-blue-900"></span>
                            </span>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <div class="font-bold text-slate-900 flex items-center gap-2">
                                    <span>Estado: {{ hist.new_status_label }}</span>
                                    <span v-if="hist.previous_status_label" class="text-xs text-slate-400 font-normal">
                                        (anterior: {{ hist.previous_status_label }})
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400 font-mono">{{ hist.created_at }}</span>
                            </div>

                            <div class="text-xs text-slate-600 mt-1 flex flex-wrap items-center gap-2">
                                <span>Responsable: <span class="font-semibold text-slate-800">{{ hist.user_name }}</span></span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-600 font-medium">
                                    {{ hist.user_role }}
                                </span>
                            </div>

                            <div v-if="hist.reason" class="mt-2 p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 italic">
                                "{{ hist.reason }}"
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-xs text-slate-500 italic py-2">
                        No hay registros adicionales de cambio de estado.
                    </div>
                </div>

            </div>
        </div>



        <!-- MODAL: CANCELAR PEDIDO (MOTIVO OBLIGATORIO) -->
        <transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showCancelModal"
                class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                @click.self="closeCancelModal"
            >
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-scale-in">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-triangle-exclamation text-rose-700"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Cancelar Pedido {{ order.folio }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                Acción definitiva &bull; Registro de motivo obligatorio
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-rose-50/70 border border-rose-200/80 text-xs text-rose-900 leading-relaxed">
                        ¿Estás seguro de cancelar este pedido? Esta acción es definitiva: el pedido no podrá volver a activarse y liberará el bloqueo de pedido activo para el cliente.
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="cancel_reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Motivo de cancelación <span class="text-rose-600">*</span>
                            </label>
                            <span class="text-[11px] text-slate-400">
                                {{ (cancelForm.reason || '').length }} / 500
                            </span>
                        </div>
                        <textarea
                            id="cancel_reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Explica el motivo por el cual se cancela el pedido (mínimo 3 caracteres)..."
                            class="w-full rounded-xl border-slate-200 text-xs sm:text-sm focus:border-rose-600 focus:ring-rose-600 transition"
                        ></textarea>
                        <InputError :message="cancelForm.errors.reason" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeCancelModal"
                            :disabled="cancelForm.processing"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer"
                        >
                            Volver
                        </button>
                        <button
                            type="button"
                            @click="submitCancellation"
                            :disabled="cancelForm.processing || !cancelForm.reason || cancelForm.reason.trim().length < 3"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition shadow-xs cursor-pointer"
                        >
                            <i v-if="cancelForm.processing" class="fa-solid fa-spinner fa-spin"></i>
                            <i v-else class="fa-solid fa-ban"></i>
                            <span>{{ cancelForm.processing ? 'Cancelando...' : 'Confirmar Cancelación' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

    </AuthenticatedLayout>
</template>