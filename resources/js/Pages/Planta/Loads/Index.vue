<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    balances: {
        type: Object,
        required: true,
    },
    fixed_ready_to_load: {
        type: Array,
        default: () => [],
    },
    pending_proposals: {
        type: Array,
        default: () => [],
    },
    transfer_history: {
        type: Array,
        default: () => [],
    },
    isOperator: {
        type: Boolean,
        default: false,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

// Pestaña activa: 'ready' | 'pending' | 'history'
const activeTab = ref('ready');

// Modal Proponer Cambio
const showExchangeModal = ref(false);
const exchangeForm = useForm({
    bottle_type: 'cambio',
    quantity: 1,
    notes: '',
});

const openExchangeModal = () => {
    if (!props.isOperator) return;
    const max = props.balances.exchange_full_available;
    exchangeForm.quantity = max > 0 ? Math.min(20, max) : 1;
    exchangeForm.notes = '';
    exchangeForm.clearErrors();
    showExchangeModal.value = true;
};

const submitExchangeProposal = () => {
    exchangeForm.post(route('planta.loads.store'), {
        onSuccess: () => {
            showExchangeModal.value = false;
        },
    });
};

// Modal Proponer Fijo
const showFixedModal = ref(false);
const selectedFixedItem = ref(null);
const fixedForm = useForm({
    bottle_type: 'fijo',
    order_item_id: null,
    quantity: 1,
    notes: '',
});

const openFixedModal = (item) => {
    if (!props.isOperator) return;
    selectedFixedItem.value = item;
    fixedForm.order_item_id = item.item_id;
    fixedForm.quantity = item.ready_to_load_quantity;
    fixedForm.notes = '';
    fixedForm.clearErrors();
    showFixedModal.value = true;
};

const submitFixedProposal = () => {
    fixedForm.post(route('planta.loads.store'), {
        onSuccess: () => {
            showFixedModal.value = false;
            selectedFixedItem.value = null;
        },
    });
};

// Retirar Propuesta
const withdrawModalOpen = ref(false);
const proposalToWithdraw = ref(null);
const withdrawForm = useForm({
    retained_quantity: 0,
    damaged_quantity: 0,
    damage_type: 'rotura',
    damage_notes: '',
    unlocated_quantity: 0,
});

const openWithdrawModal = (proposal) => {
    if (!props.isOperator) return;
    proposalToWithdraw.value = proposal;
    withdrawForm.retained_quantity = proposal.quantity;
    withdrawForm.damaged_quantity = 0;
    withdrawForm.damage_type = 'rotura';
    withdrawForm.damage_notes = '';
    withdrawForm.unlocated_quantity = 0;
    withdrawModalOpen.value = true;
};

const submitWithdraw = () => {
    if (!proposalToWithdraw.value) return;
    const total = Number(withdrawForm.retained_quantity || 0) + Number(withdrawForm.damaged_quantity || 0) + Number(withdrawForm.unlocated_quantity || 0);
    if (total !== proposalToWithdraw.value.quantity) {
        alert(`La suma del desglose (Retenidas: ${withdrawForm.retained_quantity}, Dañadas: ${withdrawForm.damaged_quantity}, Sin localizar: ${withdrawForm.unlocated_quantity}) debe ser exactamente igual a ${proposalToWithdraw.value.quantity}.`);
        return;
    }
    withdrawForm.post(route('planta.loads.withdraw', proposalToWithdraw.value.id), {
        onSuccess: () => {
            withdrawModalOpen.value = false;
            proposalToWithdraw.value = null;
        },
    });
};

const totalFixedReady = computed(() => {
    return props.fixed_ready_to_load.reduce((acc, i) => acc + i.ready_to_load_quantity, 0);
});

const getStatusBadge = (status) => {
    switch (status) {
        case 'accepted':
            return { label: 'Aceptada Completa', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
        case 'accepted_with_discrepancy':
            return { label: 'Aceptada con Diferencia', class: 'bg-amber-100 text-amber-800 border-amber-200' };
        case 'rejected':
            return { label: 'Rechazada por Chofer', class: 'bg-rose-100 text-rose-800 border-rose-200' };
        case 'cancelled':
            return { label: 'Retirada por Planta', class: 'bg-slate-100 text-slate-700 border-slate-200' };
        default:
            return { label: status, class: 'bg-slate-100 text-slate-700 border-slate-200' };
    }
};
</script>

<template>
    <Head title="Cargas al Repartidor - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-truck-ramp-box text-indigo-600"></i>
                        Cargas al Repartidor
                    </h2>
                    <p class="text-sm text-slate-500">
                        Propuestas de carga con reserva segura y custodia en planta hasta confirmación física del chofer
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="isOperator"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-800 border border-indigo-200"
                    >
                        <i class="fa-solid fa-user-gear mr-1.5 text-indigo-600"></i>
                        Operador Activo
                    </span>
                    <span
                        v-else-if="isAdmin"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                    >
                        <i class="fa-solid fa-eye mr-1.5 text-amber-600"></i>
                        Modo Supervisión
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- BANNER DE SUPERVISIÓN PARA ADMINISTRADOR -->
                <div
                    v-if="isAdmin && !isOperator"
                    class="rounded-2xl bg-amber-50 border border-amber-200 p-4 text-xs sm:text-sm text-amber-900 flex items-start gap-3 shadow-2xs"
                >
                    <i class="fa-solid fa-circle-info text-amber-600 text-base mt-0.5 shrink-0"></i>
                    <div>
                        <strong>Modo Consulta y Supervisión:</strong> Estás supervisando el despacho de cargas. Para garantizar la integridad física y la custodia, solo el Operador de Planta puede proponer cargas y solo el Repartidor puede aceptarlas.
                    </div>
                </div>

                <!-- RESUMEN DE SALDOS Y PROPUESTAS -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-4 border border-emerald-200 bg-emerald-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Cambios Llenos Libres</div>
                        <div class="text-2xl font-extrabold text-emerald-700 mt-1">
                            {{ balances.exchange_full_available }}
                        </div>
                        <div class="text-[11px] text-emerald-600 mt-0.5">Disponibles para proponer</div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-indigo-200 bg-indigo-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-indigo-800 uppercase tracking-wider">Cambios Reservados</div>
                        <div class="text-2xl font-extrabold text-indigo-700 mt-1">
                            {{ balances.exchange_full_reserved }}
                        </div>
                        <div class="text-[11px] text-indigo-600 mt-0.5">En propuestas pendientes</div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-purple-200 bg-purple-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-purple-800 uppercase tracking-wider">Fijos Preparados</div>
                        <div class="text-2xl font-extrabold text-purple-700 mt-1">
                            {{ totalFixedReady }}
                        </div>
                        <div class="text-[11px] text-purple-600 mt-0.5">Listos para devolución</div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-amber-200 bg-amber-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-amber-800 uppercase tracking-wider">Propuestas Activas</div>
                        <div class="text-2xl font-extrabold text-amber-700 mt-1">
                            {{ pending_proposals.length }}
                        </div>
                        <div class="text-[11px] text-amber-600 mt-0.5">En espera del chofer</div>
                    </div>
                </div>

                <!-- PESTAÑAS DE NAVEGACIÓN -->
                <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                    <button
                        type="button"
                        @click="activeTab = 'ready'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'ready'
                                ? 'bg-indigo-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-boxes-stacked mr-1.5"></i>
                        1. Disponibles para Carga ({{ balances.exchange_full_available }} cambio | {{ totalFixedReady }} fijos)
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'pending'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'pending'
                                ? 'bg-indigo-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-clock mr-1.5 text-amber-500"></i>
                        2. Propuestas Pendientes ({{ pending_proposals.length }})
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'history'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'history'
                                ? 'bg-indigo-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-clock-rotate-left mr-1.5 text-slate-500"></i>
                        3. Historial de Transferencias
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 1: DISPONIBLES PARA CARGA          -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'ready'" class="space-y-6">
                    <!-- 1.1 Botellones de Cambio -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-lg font-bold">
                                    <i class="fa-solid fa-bottle-water"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800">Botellones de Cambio Llenos</h3>
                                    <p class="text-xs text-slate-400">Stock general purificado y sellado listo para transferir al camión</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-800">
                                    {{ balances.exchange_full_available }} disponibles
                                </span>
                                <button
                                    type="button"
                                    @click="openExchangeModal"
                                    :disabled="!isOperator || balances.exchange_full_available <= 0"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 transition-colors cursor-pointer',
                                        isOperator && balances.exchange_full_available > 0
                                            ? 'bg-blue-700 hover:bg-blue-800 text-white shadow-2xs'
                                            : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                    ]"
                                    :title="!isOperator ? 'Solo el Operador de Planta puede proponer cargas' : 'Proponer carga de cambios'"
                                >
                                    <i class="fa-solid fa-plus"></i>
                                    Proponer Carga de Cambios
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <div>
                                <strong>Regla de Reserva:</strong> Al proponer, los garrafones se descuentan de disponibles y quedan reservados en Planta. No se suman al camión hasta que el Repartidor confirme la recepción física.
                            </div>
                            <div v-if="balances.exchange_full_reserved > 0" class="text-amber-800 font-bold shrink-0">
                                <i class="fa-solid fa-lock mr-1"></i> {{ balances.exchange_full_reserved }} en reserva activa
                            </div>
                        </div>
                    </div>

                    <!-- 1.2 Botellones Fijos Preparados por Cliente -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center text-lg font-bold">
                                    <i class="fa-solid fa-user-tag"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800">Botellones Fijos Preparados</h3>
                                    <p class="text-xs text-slate-400">Identificados por cliente propietario y pedido para su retorno exacto</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-purple-100 text-purple-800">
                                {{ totalFixedReady }} preparados listos
                            </span>
                        </div>

                        <div v-if="fixed_ready_to_load.length === 0" class="text-center py-8 text-xs text-slate-400">
                            <i class="fa-solid fa-clipboard-check text-2xl mb-2 text-slate-300 block"></i>
                            No hay botellones fijos preparados en espera de carga.
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div
                                v-for="item in fixed_ready_to_load"
                                :key="item.item_id"
                                class="border border-slate-200 rounded-xl p-4 bg-slate-50/60 hover:border-indigo-300 transition-colors flex flex-col justify-between gap-3"
                            >
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-extrabold text-slate-800">
                                                {{ item.customer_nickname || item.customer_name }}
                                            </span>
                                            <span v-if="item.customer_code" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-bold">
                                                {{ item.customer_code }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-bold">
                                            {{ item.order_folio }}
                                        </span>
                                    </div>

                                    <div class="text-xs text-slate-600 flex items-center gap-3">
                                        <span>Preparados: <strong class="text-slate-800">{{ item.prepared_quantity }}</strong></span>
                                        <span v-if="item.loaded_quantity > 0" class="text-slate-400">({{ item.loaded_quantity }} ya en ruta)</span>
                                        <span v-if="item.reserved_quantity > 0" class="text-amber-700 font-semibold">
                                            <i class="fa-solid fa-lock text-[10px]"></i> {{ item.reserved_quantity }} reservados
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-200/80">
                                    <span class="text-xs font-extrabold text-purple-800">
                                        {{ item.ready_to_load_quantity }} unidad(es) libres para cargar
                                    </span>
                                    <button
                                        type="button"
                                        @click="openFixedModal(item)"
                                        :disabled="!isOperator"
                                        :class="[
                                            'px-3.5 py-1.5 rounded-lg text-xs font-bold inline-flex items-center gap-1.5 transition-colors cursor-pointer',
                                            isOperator
                                                ? 'bg-purple-700 hover:bg-purple-800 text-white shadow-2xs'
                                                : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                        ]"
                                        :title="!isOperator ? 'Solo el Operador de Planta puede proponer cargas' : 'Proponer carga al repartidor'"
                                    >
                                        <i class="fa-solid fa-dolly"></i>
                                        Proponer Carga
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 2: PROPUESTAS PENDIENTES           -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'pending'" class="space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-800">Transferencias Propuestas en Espera</h3>
                                <p class="text-xs text-slate-400">
                                    Permanecen bajo custodia de Planta hasta que el Repartidor confirme la recepción física en su unidad
                                </p>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                {{ pending_proposals.length }} pendientes
                            </span>
                        </div>

                        <div v-if="pending_proposals.length === 0" class="text-center py-10 text-xs text-slate-400">
                            <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 text-slate-300 block"></i>
                            No hay propuestas pendientes de confirmación.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="prop in pending_proposals"
                                :key="prop.id"
                                class="border border-amber-200 rounded-xl p-4 bg-amber-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                                                prop.bottle_type === 'cambio' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'
                                            ]"
                                        >
                                            {{ prop.bottle_type === 'cambio' ? 'Cambio Lleno' : 'Fijo de Cliente' }}
                                        </span>
                                        <span class="text-sm font-extrabold text-slate-900">
                                            {{ prop.quantity }} unidades
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            • Creado: {{ prop.created_at }} por {{ prop.initiator_name }}
                                        </span>
                                    </div>

                                    <div v-if="prop.customer_name" class="text-xs text-slate-700">
                                        <strong>Cliente:</strong> {{ prop.customer_nickname || prop.customer_name }}
                                        <span v-if="prop.customer_code" class="text-[10px] font-mono px-1 bg-slate-200 rounded">({{ prop.customer_code }})</span>
                                        <span v-if="prop.order_folio" class="ml-2 font-mono text-[11px] text-indigo-700">[{{ prop.order_folio }}]</span>
                                    </div>

                                    <p v-if="prop.notes" class="text-xs text-slate-500 italic">
                                        Nota: {{ prop.notes }}
                                    </p>
                                </div>

                                <div class="shrink-0 flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                        <i class="fa-solid fa-hourglass-half text-[10px]"></i> En espera de chofer
                                    </span>

                                    <button
                                        v-if="isOperator"
                                        type="button"
                                        @click="openWithdrawModal(prop)"
                                        class="px-3 py-1 rounded-lg text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors cursor-pointer"
                                        title="Retirar propuesta antes de que sea aceptada y conciliar reservas"
                                    >
                                        <i class="fa-solid fa-rotate-left mr-1"></i> Retirar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- PESTAÑA 3: HISTORIAL DE TRANSFERENCIAS     -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'history'" class="space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                        <div class="border-b border-slate-100 pb-3 mb-4">
                            <h3 class="text-base font-bold text-slate-800">Bitácora de Cargas Despachadas</h3>
                            <p class="text-xs text-slate-400">
                                Registro inmutable de transferencias aceptadas, con diferencia, rechazadas y canceladas
                            </p>
                        </div>

                        <div v-if="transfer_history.length === 0" class="text-center py-10 text-xs text-slate-400">
                            Aún no hay transferencias registradas en el historial.
                        </div>

                        <div v-else class="space-y-2.5">
                            <div
                                v-for="t in transfer_history"
                                :key="t.id"
                                class="border border-slate-200 rounded-xl p-3.5 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-slate-800 text-sm">
                                            {{ t.accepted_quantity }} de {{ t.proposed_quantity }} u.
                                        </span>
                                        <span class="text-slate-500 uppercase font-bold text-[10px]">
                                            ({{ t.bottle_type }})
                                        </span>
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                                getStatusBadge(t.status).class
                                            ]"
                                        >
                                            {{ getStatusBadge(t.status).label }}
                                        </span>
                                    </div>

                                    <div class="text-slate-600">
                                        <template v-if="t.customer_name">
                                            Cliente: <strong>{{ t.customer_name }}</strong>
                                            <span v-if="t.order_folio"> • Pedido: {{ t.order_folio }}</span>
                                            <span class="mx-1">•</span>
                                        </template>
                                        <span>Despachó: {{ t.initiator_name }}</span>
                                        <span v-if="t.driver_name"> • Recibió chofer: {{ t.driver_name }}</span>
                                    </div>

                                    <div v-if="t.difference_reason" class="text-amber-700 bg-amber-50 p-1.5 rounded border border-amber-200 text-[11px]">
                                        <strong>Motivo de diferencia/rechazo:</strong> {{ t.difference_reason }}
                                    </div>
                                </div>

                                <div class="text-right text-[11px] text-slate-400 shrink-0">
                                    <div>Propuesto: {{ t.created_at }}</div>
                                    <div v-if="t.confirmed_at" class="text-slate-600">Procesado: {{ t.confirmed_at }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: PROPONER CARGA DE CAMBIOS           -->
        <!-- ========================================== -->
        <div v-if="showExchangeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-bottle-water text-blue-600"></i>
                        Proponer Carga de Cambios Llenos
                    </h3>
                    <button @click="showExchangeModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="text-xs bg-blue-50 p-3 rounded-xl border border-blue-200 text-blue-900 space-y-1">
                    <div>Disponibles en Planta: <strong>{{ balances.exchange_full_available }} garrafones</strong></div>
                    <p class="text-[11px] text-blue-700">
                        Al confirmar, los garrafones quedarán reservados en Planta hasta que el Repartidor los acepte en "Mi carga".
                    </p>
                </div>

                <form @submit.prevent="submitExchangeProposal" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad a proponer *
                        </label>
                        <input
                            v-model.number="exchangeForm.quantity"
                            type="number"
                            min="1"
                            :max="balances.exchange_full_available"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span v-if="exchangeForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ exchangeForm.errors.quantity }}
                        </span>
                        <span v-if="exchangeForm.errors.load" class="text-xs text-rose-600 mt-1 block">
                            {{ exchangeForm.errors.load }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Notas para el repartidor (opcional)
                        </label>
                        <input
                            v-model="exchangeForm.notes"
                            type="text"
                            placeholder="Ej. Carga matutina para ruta norte"
                            class="w-full rounded-xl border-slate-300 text-xs focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showExchangeModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="exchangeForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-700 hover:bg-blue-800 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Enviar Propuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal de Retiro de Propuesta con Desglose Físico -->
        <div
            v-if="withdrawModalOpen"
            class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-rotate-left text-rose-600"></i>
                        Retirar Propuesta de Carga
                    </h3>
                    <button
                        type="button"
                        @click="withdrawModalOpen = false"
                        class="text-slate-400 hover:text-slate-600 cursor-pointer"
                    >
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="proposalToWithdraw" class="text-xs bg-rose-50 p-3 rounded-xl border border-rose-200 text-rose-900 space-y-1">
                    <div>Propuesta: <strong>{{ proposalToWithdraw.bottle_type_label }} ({{ proposalToWithdraw.quantity }} botellones)</strong></div>
                    <div v-if="proposalToWithdraw.customer_name">Cliente: <strong>{{ proposalToWithdraw.customer_name }}</strong></div>
                    <p class="text-[11px] text-rose-700">
                        Indica el estado físico real de los botellones para conciliar las existencias y reservas de Planta sin pérdida de trazabilidad.
                    </p>
                </div>

                <form @submit.prevent="submitWithdraw" class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                Retenidos Aptos
                            </label>
                            <input
                                v-model.number="withdrawForm.retained_quantity"
                                type="number"
                                min="0"
                                :max="proposalToWithdraw ? proposalToWithdraw.quantity : 0"
                                required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500 text-center font-bold"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">Se liberan a disponibles</p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                Dañados
                            </label>
                            <input
                                v-model.number="withdrawForm.damaged_quantity"
                                type="number"
                                min="0"
                                :max="proposalToWithdraw ? proposalToWithdraw.quantity : 0"
                                required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500 text-center font-bold text-rose-600"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">Envases rotos/dañados</p>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                Sin Localizar
                            </label>
                            <input
                                v-model.number="withdrawForm.unlocated_quantity"
                                type="number"
                                min="0"
                                :max="proposalToWithdraw ? proposalToWithdraw.quantity : 0"
                                required
                                class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500 text-center font-bold text-amber-600"
                            />
                            <p class="text-[10px] text-slate-400 mt-1">Pendientes de aclarar</p>
                        </div>
                    </div>

                    <div v-if="withdrawForm.damaged_quantity > 0" class="space-y-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                Tipo de daño detectado
                            </label>
                            <select
                                v-model="withdrawForm.damage_type"
                                class="w-full rounded-xl border-slate-300 text-xs focus:border-rose-500 focus:ring-rose-500"
                            >
                                <option value="rotura">Rotura</option>
                                <option value="fisura">Fisura / Microfisura</option>
                                <option value="contaminacion">Contaminación</option>
                                <option value="deformacion">Deformación</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                                Detalle del daño
                            </label>
                            <input
                                v-model="withdrawForm.damage_notes"
                                type="text"
                                placeholder="Describe el daño o rotura al manipular"
                                class="w-full rounded-xl border-slate-300 text-xs focus:border-rose-500 focus:ring-rose-500"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="withdrawModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="withdrawForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Confirmar Retiro
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: PROPONER CARGA DE FIJOS             -->
        <!-- ========================================== -->
        <div v-if="showFixedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-user-tag text-purple-600"></i>
                        Proponer Carga de Fijos Preparados
                    </h3>
                    <button @click="showFixedModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedFixedItem" class="text-xs bg-purple-50 p-3 rounded-xl border border-purple-200 text-purple-900 space-y-1">
                    <div>Cliente: <strong>{{ selectedFixedItem.customer_nickname || selectedFixedItem.customer_name }}</strong></div>
                    <div>Pedido: <strong>{{ selectedFixedItem.order_folio }}</strong></div>
                    <div>Preparados libres para cargar: <strong>{{ selectedFixedItem.ready_to_load_quantity }}</strong></div>
                </div>

                <form @submit.prevent="submitFixedProposal" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad a proponer para retorno al cliente *
                        </label>
                        <input
                            v-model.number="fixedForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedFixedItem ? selectedFixedItem.ready_to_load_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-purple-500 focus:ring-purple-500"
                        />
                        <span v-if="fixedForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ fixedForm.errors.quantity }}
                        </span>
                        <span v-if="fixedForm.errors.load" class="text-xs text-rose-600 mt-1 block">
                            {{ fixedForm.errors.load }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Notas para el repartidor (opcional)
                        </label>
                        <input
                            v-model="fixedForm.notes"
                            type="text"
                            placeholder="Ej. Garrafones con tapa azul etiquetados"
                            class="w-full rounded-xl border-slate-300 text-xs focus:border-purple-500 focus:ring-purple-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showFixedModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="fixedForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-purple-700 hover:bg-purple-800 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Enviar Propuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

