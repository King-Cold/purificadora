<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    pendingReceptions: {
        type: Object,
        required: true,
    },
    canConfirmReception: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

// ----------------------------------------------------
// Modal y Formulario: Recepción de Fijos
// ----------------------------------------------------
const showFixedModal = ref(false);
const activeFixedItem = ref(null);
const hasDamageInFixed = ref(false);

const fixedForm = useForm({
    order_item_id: null,
    received_quantity: 0,
    start_washing: true,
    damaged_quantity: 0,
    damage_type: 'fisura',
    damage_notes: '',
    difference_reason: '',
});

const openFixedReceptionModal = (item) => {
    activeFixedItem.value = item;
    fixedForm.order_item_id = item.order_item_id;
    fixedForm.received_quantity = item.pending_quantity;
    fixedForm.start_washing = true;
    fixedForm.damaged_quantity = 0;
    fixedForm.damage_type = 'fisura';
    fixedForm.damage_notes = '';
    fixedForm.difference_reason = '';
    hasDamageInFixed.value = false;
    showFixedModal.value = true;
};

const fixedShortage = computed(() => {
    if (!activeFixedItem.value) return 0;
    const handled = Number(fixedForm.received_quantity || 0) + (hasDamageInFixed.value ? Number(fixedForm.damaged_quantity || 0) : 0);
    return Math.max(0, activeFixedItem.value.pending_quantity - handled);
});

const submitFixedReception = (startWashing = false) => {
    fixedForm.start_washing = startWashing;
    if (!hasDamageInFixed.value) {
        fixedForm.damaged_quantity = 0;
        fixedForm.damage_notes = '';
    }

    fixedForm.post(route('planta.reception.confirm-fixed'), {
        preserveScroll: true,
        onSuccess: () => {
            showFixedModal.value = false;
            activeFixedItem.value = null;
        },
    });
};

// ----------------------------------------------------
// Modal y Formulario: Recepción de Cambios
// ----------------------------------------------------
const showExchangeModal = ref(false);
const activeExchangeMovement = ref(null);
const hasDamageInExchange = ref(false);

const exchangeForm = useForm({
    movement_id: null,
    received_quantity: 0,
    damaged_quantity: 0,
    damage_type: 'rotura',
    damage_notes: '',
    difference_reason: '',
});

const openExchangeReceptionModal = (movement) => {
    activeExchangeMovement.value = movement;
    exchangeForm.movement_id = movement.movement_id;
    exchangeForm.received_quantity = movement.quantity_declared;
    exchangeForm.damaged_quantity = 0;
    exchangeForm.damage_type = 'rotura';
    exchangeForm.damage_notes = '';
    exchangeForm.difference_reason = '';
    hasDamageInExchange.value = false;
    showExchangeModal.value = true;
};

const exchangeShortage = computed(() => {
    if (!activeExchangeMovement.value) return 0;
    const handled = Number(exchangeForm.received_quantity || 0) + (hasDamageInExchange.value ? Number(exchangeForm.damaged_quantity || 0) : 0);
    return Math.max(0, activeExchangeMovement.value.quantity_declared - handled);
});

const submitExchangeReception = () => {
    if (!hasDamageInExchange.value) {
        exchangeForm.damaged_quantity = 0;
        exchangeForm.damage_notes = '';
    }

    exchangeForm.post(route('planta.reception.confirm-exchange'), {
        preserveScroll: true,
        onSuccess: () => {
            showExchangeModal.value = false;
            activeExchangeMovement.value = null;
        },
    });
};

// ----------------------------------------------------
// Comprobante / Recibo de Recepción
// ----------------------------------------------------
const showReceiptModal = ref(true);
const dismissReceipt = () => {
    showReceiptModal.value = false;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Por Recibir de Ruta - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800">
                        Por Recibir de Ruta
                    </h2>
                    <p class="text-sm text-slate-500">
                        Cotejo físico presencial de fijos y cambios entregados por el chofer
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                        <i class="fa-solid fa-dolly mr-1.5 text-amber-600"></i>
                        {{ pendingReceptions.total_pending_count }} entrega(s) por cotejar
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- FLASH MESSAGES -->
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm font-semibold flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- COMPROBANTE DE RECEPCIÓN CONFIRMADA -->
                <div
                    v-if="$page.props.flash?.receipt && showReceiptModal"
                    class="rounded-2xl bg-white border-2 border-emerald-500 p-6 shadow-md"
                >
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-bold shrink-0">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs uppercase font-extrabold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                                        Comprobante de Recepción Física
                                    </span>
                                    <span class="text-xs font-mono font-bold text-slate-500">
                                        {{ $page.props.flash.receipt.receipt_folio }}
                                    </span>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 mt-0.5">
                                    {{ $page.props.flash.receipt.customer }}
                                </h3>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="dismissReceipt"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        >
                            Cerrar comprobante &times;
                        </button>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 py-4 text-xs">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                            <span class="text-slate-500 block">Declarado en camión:</span>
                            <span class="text-base font-bold text-slate-800">{{ $page.props.flash.receipt.proposed_quantity }} u.</span>
                        </div>
                        <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-200/60">
                            <span class="text-emerald-700 block font-semibold">Recibido apto:</span>
                            <span class="text-base font-bold text-emerald-800">{{ $page.props.flash.receipt.received_quantity }} u.</span>
                        </div>
                        <div class="bg-rose-50 p-3 rounded-xl border border-rose-200/60">
                            <span class="text-rose-700 block font-semibold">Dañado en recepción:</span>
                            <span class="text-base font-bold text-rose-800">{{ $page.props.flash.receipt.damaged_quantity }} u.</span>
                        </div>
                        <div class="bg-amber-50 p-3 rounded-xl border border-amber-200/60">
                            <span class="text-amber-700 block font-semibold">Faltante / Discrepancia:</span>
                            <span class="text-base font-bold text-amber-800">{{ $page.props.flash.receipt.shortage_quantity }} u.</span>
                        </div>
                    </div>

                    <div v-if="$page.props.flash.receipt.difference_reason" class="text-xs text-amber-900 bg-amber-50/70 p-3 rounded-xl border border-amber-200">
                        <strong>Discrepancia registrada:</strong> {{ $page.props.flash.receipt.difference_reason }}
                    </div>

                    <div class="text-[11px] text-slate-400 mt-3 flex items-center justify-between">
                        <span>Recibido por: <strong>{{ $page.props.flash.receipt.operator_name }}</strong> el {{ $page.props.flash.receipt.timestamp }}</span>
                        <span v-if="$page.props.flash.receipt.washing_started" class="text-emerald-700 font-bold">
                            <i class="fa-solid fa-soap mr-1"></i> Ingresado a lavado
                        </span>
                    </div>
                </div>

                <!-- SECCIÓN 1: FIJOS VACÍOS POR CLIENTE (RECOLECCIÓN EN RUTA) -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-purple-600"></span>
                                <h3 class="text-base font-bold text-slate-900">
                                    Botellones Fijos de Clientes
                                </h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Envases recolectados en domicilio en camino a planta. Coteja cantidades físicas antes de ingresarlos a lavado.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-800 border border-purple-200">
                            {{ pendingReceptions.fixed_pending.length }} entrega(s) de fijos
                        </span>
                    </div>

                    <div v-if="pendingReceptions.fixed_pending.length === 0" class="p-8 text-center text-sm text-slate-400">
                        <i class="fa-solid fa-check-double text-3xl mb-2 text-slate-300 block"></i>
                        No hay botellones fijos pendientes de recepción en este momento.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Cliente Propietario</th>
                                    <th class="px-4 py-3">Pedido</th>
                                    <th class="px-4 py-3 text-center">Recolectados Chofer</th>
                                    <th class="px-4 py-3 text-center">Recibidos Previo</th>
                                    <th class="px-4 py-3 text-center">Pendiente de Cotejo</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="item in pendingReceptions.fixed_pending"
                                    :key="item.order_item_id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-slate-900">
                                            {{ item.customer_nickname || item.customer_name || 'Sin apodo' }}
                                        </div>
                                        <div class="text-xs text-slate-400 font-mono">
                                            {{ item.customer_code }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-xs font-bold text-blue-700">
                                        {{ item.order_folio }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center font-bold text-slate-800">
                                        {{ item.collected_quantity }} u.
                                    </td>
                                    <td class="px-4 py-3.5 text-center text-slate-500">
                                        {{ item.received_at_plant_quantity }} u.
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-extrabold bg-purple-100 text-purple-900">
                                            {{ item.pending_quantity }} u.
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <button
                                            v-if="canConfirmReception"
                                            type="button"
                                            @click="openFixedReceptionModal(item)"
                                            class="inline-flex items-center px-3.5 py-1.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs shadow-xs transition-colors"
                                        >
                                            <i class="fa-solid fa-clipboard-check mr-1.5"></i>
                                            Revisar recepción
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN 2: CAMBIOS VACÍOS (DESCARGA DE CAMIÓN) -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                                <h3 class="text-base font-bold text-slate-900">
                                    Garrafones de Cambio Vacíos
                                </h3>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Propuestas de descarga de botellones de cambio genéricos realizadas por el repartidor.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-800 border border-blue-200">
                            {{ pendingReceptions.exchange_pending.length }} descarga(s) de cambio
                        </span>
                    </div>

                    <div v-if="pendingReceptions.exchange_pending.length === 0" class="p-8 text-center text-sm text-slate-400">
                        <i class="fa-solid fa-check-double text-3xl mb-2 text-slate-300 block"></i>
                        No hay descargas de cambios vacíos pendientes en este momento.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Referencia</th>
                                    <th class="px-4 py-3">Fecha Declarada</th>
                                    <th class="px-4 py-3">Chofer</th>
                                    <th class="px-4 py-3 text-center">Declarado en Camión</th>
                                    <th class="px-4 py-3">Notas</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="mov in pendingReceptions.exchange_pending"
                                    :key="mov.movement_id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3.5 font-mono text-xs font-bold text-blue-700">
                                        {{ mov.reference }}
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                                        {{ mov.created_at }}
                                    </td>
                                    <td class="px-4 py-3.5 font-semibold text-slate-800">
                                        {{ mov.driver_name }}
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-900">
                                            {{ mov.quantity_declared }} vacíos
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-500 truncate max-w-xs">
                                        {{ mov.notes || '-' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <button
                                            v-if="canConfirmReception"
                                            type="button"
                                            @click="openExchangeReceptionModal(mov)"
                                            class="inline-flex items-center px-3.5 py-1.5 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-xs transition-colors"
                                        >
                                            <i class="fa-solid fa-clipboard-check mr-1.5"></i>
                                            Revisar recepción
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN 3: HISTORIAL DE RECEPCIONES RECIENTES -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                            Últimas Recepciones Físicas Confirmadas
                        </h3>
                    </div>

                    <div v-if="pendingReceptions.recent_receptions.length === 0" class="p-8 text-center text-sm text-slate-400">
                        No hay recepciones físicas registradas aún.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Tipo</th>
                                    <th class="px-4 py-3">Origen &rarr; Destino</th>
                                    <th class="px-4 py-3 text-center">Cant. Recibida</th>
                                    <th class="px-4 py-3">Confirmado Por</th>
                                    <th class="px-4 py-3">Discrepancias / Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <tr
                                    v-for="mov in pendingReceptions.recent_receptions"
                                    :key="mov.id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        {{ formatDate(mov.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="mov.bottle_type === 'fijo' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                                            class="px-2 py-0.5 rounded text-[11px] font-bold uppercase"
                                        >
                                            {{ mov.bottle_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ mov.origin }} &rarr; {{ mov.destination }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-900">
                                        {{ mov.quantity }} u.
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ mov.confirmed_by_user?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 truncate max-w-sm" :title="mov.difference_reason || mov.notes">
                                        <span v-if="mov.difference_reason" class="text-amber-700 font-bold block">
                                            {{ mov.difference_reason }}
                                        </span>
                                        <span>{{ mov.notes }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: REVISAR RECEPCIÓN DE FIJOS                            -->
        <!-- ============================================================= -->
        <div
            v-if="showFixedModal && activeFixedItem"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="h-9 w-9 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-900">
                                Revisar Recepción de Fijos
                            </h4>
                            <p class="text-xs text-slate-500">
                                {{ activeFixedItem.customer_nickname || activeFixedItem.customer_name }} ({{ activeFixedItem.customer_code }})
                            </p>
                        </div>
                    </div>
                    <button @click="showFixedModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <!-- Resumen de lo declarado -->
                <div class="bg-purple-50/70 p-4 rounded-xl border border-purple-200 text-xs space-y-1">
                    <div class="flex justify-between">
                        <span class="text-purple-900">Pedido asociado:</span>
                        <strong class="font-mono text-purple-950">{{ activeFixedItem.order_folio }}</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-purple-900">Total recolectado por chofer:</span>
                        <strong class="text-purple-950">{{ activeFixedItem.collected_quantity }} u.</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-purple-900">Previamente recibido en planta:</span>
                        <span class="text-slate-600">{{ activeFixedItem.received_at_plant_quantity }} u.</span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-purple-200 font-bold text-sm">
                        <span class="text-purple-950">Pendiente de cotejo actual:</span>
                        <span class="text-purple-800">{{ activeFixedItem.pending_quantity }} u.</span>
                    </div>
                </div>

                <form @submit.prevent class="space-y-4 text-sm">
                    <!-- Cantidad apta recibida -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad Apta Recibida Físicamente *
                        </label>
                        <input
                            v-model.number="fixedForm.received_quantity"
                            type="number"
                            min="0"
                            :max="activeFixedItem.pending_quantity"
                            required
                            class="w-full rounded-xl border-slate-300 font-extrabold text-slate-900 focus:border-purple-500 focus:ring-purple-500"
                        />
                        <span v-if="fixedForm.errors.received_quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ fixedForm.errors.received_quantity }}
                        </span>
                    </div>

                    <!-- Checkbox de Daños detectados en recepción -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="hasDamageInFixed"
                                type="checkbox"
                                class="rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                            />
                            <span class="text-xs font-bold text-slate-800">
                                ¿Se detectaron envases dañados al recibir? (Incidencia)
                            </span>
                        </label>

                        <div v-if="hasDamageInFixed" class="space-y-3 pt-2 border-t border-slate-200">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                        Cantidad Dañada *
                                    </label>
                                    <input
                                        v-model.number="fixedForm.damaged_quantity"
                                        type="number"
                                        min="1"
                                        :max="activeFixedItem.pending_quantity - fixedForm.received_quantity"
                                        required
                                        class="w-full rounded-lg border-slate-300 font-bold text-rose-600 text-xs"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                        Tipo de Daño *
                                    </label>
                                    <select
                                        v-model="fixedForm.damage_type"
                                        class="w-full rounded-lg border-slate-300 text-xs"
                                    >
                                        <option value="fisura">Fisura o fuga</option>
                                        <option value="rotura">Rotura visible</option>
                                        <option value="contaminacion">Contaminación u olor</option>
                                        <option value="otro">Otro defecto</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                    Descripción del Daño *
                                </label>
                                <input
                                    v-model="fixedForm.damage_notes"
                                    type="text"
                                    minlength="5"
                                    placeholder="Detalle de la rotura o fisura..."
                                    class="w-full rounded-lg border-slate-300 text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Alerta de Discrepancia si hay faltantes -->
                    <div v-if="fixedShortage > 0" class="p-3 bg-amber-50 rounded-xl border border-amber-300 space-y-2">
                        <div class="flex items-center gap-2 text-amber-900 font-bold text-xs">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                            Faltan {{ fixedShortage }} envase(s) respecto a lo declarado
                        </div>
                        <p class="text-[11px] text-amber-800">
                            Esta cantidad permanecerá identificada como discrepancia pendiente de aclaración.
                        </p>
                        <div>
                            <label class="block text-[11px] font-bold text-amber-900 uppercase mb-1">
                                Motivo Obligatorio de la Discrepancia *
                            </label>
                            <input
                                v-model="fixedForm.difference_reason"
                                type="text"
                                required
                                minlength="5"
                                placeholder="Ej: Chofer reporta 1 quebrado en ruta / extraviado..."
                                class="w-full rounded-lg border-amber-300 text-xs focus:border-amber-500 focus:ring-amber-500"
                            />
                            <span v-if="fixedForm.errors.difference_reason" class="text-xs text-rose-600 mt-1 block">
                                {{ fixedForm.errors.difference_reason }}
                            </span>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showFixedModal = false"
                            class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="submitFixedReception(false)"
                            :disabled="fixedForm.processing"
                            class="w-full sm:w-auto px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs transition-colors"
                        >
                            Confirmar Recepción
                        </button>
                        <button
                            type="button"
                            @click="submitFixedReception(true)"
                            :disabled="fixedForm.processing"
                            class="w-full sm:w-auto px-5 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-1.5"
                        >
                            <i class="fa-solid fa-soap text-purple-200"></i>
                            Confirmar e Iniciar Lavado
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: REVISAR RECEPCIÓN DE CAMBIOS                          -->
        <!-- ============================================================= -->
        <div
            v-if="showExchangeModal && activeExchangeMovement"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="h-9 w-9 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-900">
                                Recepción de Garrafones de Cambio
                            </h4>
                            <p class="text-xs text-slate-500">
                                {{ activeExchangeMovement.reference }} - Chofer: {{ activeExchangeMovement.driver_name }}
                            </p>
                        </div>
                    </div>
                    <button @click="showExchangeModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <div class="bg-blue-50/70 p-4 rounded-xl border border-blue-200 text-xs flex justify-between items-center font-bold">
                    <span class="text-blue-900">Declarado por el repartidor:</span>
                    <span class="text-blue-950 text-sm">{{ activeExchangeMovement.quantity_declared }} vacíos</span>
                </div>

                <form @submit.prevent="submitExchangeReception" class="space-y-4 text-sm">
                    <!-- Cantidad apta recibida -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad Apta Recibida Físicamente *
                        </label>
                        <input
                            v-model.number="exchangeForm.received_quantity"
                            type="number"
                            min="0"
                            :max="activeExchangeMovement.quantity_declared"
                            required
                            class="w-full rounded-xl border-slate-300 font-extrabold text-slate-900 focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span v-if="exchangeForm.errors.received_quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ exchangeForm.errors.received_quantity }}
                        </span>
                    </div>

                    <!-- Checkbox de Daños detectados en recepción -->
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="hasDamageInExchange"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-xs font-bold text-slate-800">
                                ¿Se detectaron garrafones dañados / rotos al descargar?
                            </span>
                        </label>

                        <div v-if="hasDamageInExchange" class="space-y-3 pt-2 border-t border-slate-200">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                        Cantidad Dañada *
                                    </label>
                                    <input
                                        v-model.number="exchangeForm.damaged_quantity"
                                        type="number"
                                        min="1"
                                        :max="activeExchangeMovement.quantity_declared - exchangeForm.received_quantity"
                                        required
                                        class="w-full rounded-lg border-slate-300 font-bold text-rose-600 text-xs"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                        Tipo de Daño *
                                    </label>
                                    <select
                                        v-model="exchangeForm.damage_type"
                                        class="w-full rounded-lg border-slate-300 text-xs"
                                    >
                                        <option value="rotura">Rotura o quebrado</option>
                                        <option value="fisura">Fisura o fuga</option>
                                        <option value="contaminacion">Contaminación</option>
                                        <option value="otro">Otro defecto</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">
                                    Descripción del Daño *
                                </label>
                                <input
                                    v-model="exchangeForm.damage_notes"
                                    type="text"
                                    minlength="5"
                                    placeholder="Detalle de la rotura..."
                                    class="w-full rounded-lg border-slate-300 text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Alerta de Discrepancia si hay faltantes -->
                    <div v-if="exchangeShortage > 0" class="p-3 bg-amber-50 rounded-xl border border-amber-300 space-y-2">
                        <div class="flex items-center gap-2 text-amber-900 font-bold text-xs">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                            Faltan {{ exchangeShortage }} garrafón(es) respecto a lo declarado
                        </div>
                        <p class="text-[11px] text-amber-800">
                            Esta cantidad no ingresará a las existencias de planta y quedará asentada como discrepancia.
                        </p>
                        <div>
                            <label class="block text-[11px] font-bold text-amber-900 uppercase mb-1">
                                Motivo Obligatorio de la Discrepancia *
                            </label>
                            <input
                                v-model="exchangeForm.difference_reason"
                                type="text"
                                required
                                minlength="5"
                                placeholder="Ej: No se descargaron todos / envases dañados en ruta..."
                                class="w-full rounded-lg border-amber-300 text-xs focus:border-amber-500 focus:ring-amber-500"
                            />
                            <span v-if="exchangeForm.errors.difference_reason" class="text-xs text-rose-600 mt-1 block">
                                {{ exchangeForm.errors.difference_reason }}
                            </span>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showExchangeModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="exchangeForm.processing"
                            class="px-5 py-2 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-bold text-xs shadow-xs transition-colors"
                        >
                            Confirmar Entrada a Planta
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

