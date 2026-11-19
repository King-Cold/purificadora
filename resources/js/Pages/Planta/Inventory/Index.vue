<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { alertWarning } from '@/utils/swal';
import {
    AlertTriangle,
    Boxes,
    ClipboardCheck,
    Factory,
    Pencil,
    RefreshCw,
    Tag,
    Truck,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    inventoryState: { type: Object, required: true },
    customersWithFixed: { type: Array, default: () => [] },
    canRecordInitialCount: { type: Boolean, default: false },
    canReportIncident: { type: Boolean, default: false },
    canEditTarget: { type: Boolean, default: false },
});

const page = usePage();

// ----------------------------------------------------
// Conteo Inicial Físico (solo si está pendiente)
// ----------------------------------------------------
const showInitialCountConfirmModal = ref(false);
const initialCountForm = useForm({
    empty_count: '',
    full_count: '',
    notes: '',
});

const openInitialCountConfirmation = () => {
    if (initialCountForm.empty_count === '' || initialCountForm.full_count === '') {
        alertWarning('Completa las cantidades de botellones vacíos y llenos.');
        return;
    }
    showInitialCountConfirmModal.value = true;
};

const submitInitialCount = () => {
    initialCountForm.post(route('planta.inventory.initial-count'), {
        preserveScroll: true,
        onSuccess: () => {
            showInitialCountConfirmModal.value = false;
            initialCountForm.reset();
        },
    });
};

// ----------------------------------------------------
// Ajustar / Reiniciar conteo físico (Planta y Camión)
// ----------------------------------------------------
const showRecountModal = ref(false);
const recountForm = useForm({
    empty_count: props.inventoryState?.balances?.exchange_empty_available ?? 0,
    washing_count: 0,
    full_count: props.inventoryState?.balances?.exchange_full_available ?? 0,
    truck_full_count: props.inventoryState?.balances?.truck_full_available ?? 0,
    truck_empty_count: props.inventoryState?.balances?.truck_empty_collected ?? 0,
    reason: 'Ajuste de inventario físico',
    notes: '',
    sync_target: false,
});

const openRecountModal = () => {
    const b = props.inventoryState?.balances || {};
    recountForm.empty_count = Number(b.exchange_empty_available ?? 0);
    recountForm.washing_count = 0;
    recountForm.full_count = Number(b.exchange_full_available ?? 0);
    recountForm.truck_full_count = Number(b.truck_full_available ?? 0);
    recountForm.truck_empty_count = Number(b.truck_empty_collected ?? 0);
    recountForm.reason = 'Ajuste de inventario físico';
    recountForm.notes = '';
    recountForm.sync_target = false;
    recountForm.clearErrors();
    showRecountModal.value = true;
};

const resetRecountToZero = () => {
    recountForm.empty_count = 0;
    recountForm.washing_count = 0;
    recountForm.full_count = 0;
    recountForm.truck_full_count = 0;
    recountForm.truck_empty_count = 0;
    recountForm.reason = 'Reinicio total de conteo';
};

const recountPlantTotal = computed(() => {
    const reserved = Number(props.inventoryState?.balances?.exchange_full_reserved ?? 0);
    return (
        Math.max(0, Number(recountForm.empty_count || 0)) +
        Math.max(0, Number(recountForm.full_count || 0)) +
        reserved
    );
});

const recountTruckTotal = computed(() => {
    return (
        Math.max(0, Number(recountForm.truck_full_count || 0)) +
        Math.max(0, Number(recountForm.truck_empty_count || 0))
    );
});

const recountCircuitTotal = computed(() => recountPlantTotal.value + recountTruckTotal.value);

const submitRecount = () => {
    if (recountForm.processing) return;
    recountForm.post(route('planta.inventory.reconcile'), {
        preserveScroll: true,
        onSuccess: () => {
            showRecountModal.value = false;
        },
    });
};

// ----------------------------------------------------
// Reporte rápido de daño para Fijos en custodia
// ----------------------------------------------------
const showFixedIncidentModal = ref(false);
const fixedIncidentCustomerLabel = ref('');
const incidentForm = useForm({
    bottle_type: 'fijo',
    customer_id: '',
    order_item_id: '',
    quantity: 1,
    stage: 'recepcion',
    bottle_condition: 'empty',
    location_status: 'separado',
    damage_type: 'rotura',
    notes: '',
});

const openFixedIncidentModal = (fixedItem) => {
    incidentForm.clearErrors();
    incidentForm.bottle_type = 'fijo';
    incidentForm.customer_id = fixedItem.customer_id;
    incidentForm.order_item_id = fixedItem.order_item_id;
    incidentForm.stage = fixedItem.ready_full > 0 ? 'almacen' : 'recepcion';
    incidentForm.bottle_condition = fixedItem.ready_full > 0 ? 'full' : 'empty';
    incidentForm.quantity = 1;
    incidentForm.damage_type = 'rotura';
    incidentForm.notes = '';
    fixedIncidentCustomerLabel.value = fixedItem.customer_nickname || fixedItem.customer_name || 'Cliente';
    showFixedIncidentModal.value = true;
};

const submitFixedIncident = () => {
    if (incidentForm.processing) return;
    incidentForm.post(route('planta.inventory.incidents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showFixedIncidentModal.value = false;
            incidentForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Existencias - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- 1. ENCABEZADO -->
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shadow-sm">
                            <Boxes class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1E1545]">
                                Existencias
                            </h1>
                            <p class="text-xs sm:text-sm text-[#747181]">
                                Inventario físico actual de Cambios y Fijos en custodia de Planta
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('planta.incidents.index')"
                            class="h-10 px-3.5 rounded-xl bg-white hover:bg-[#FEF6E7] text-[#9A630C] border border-[#E9E7F0] font-semibold text-xs sm:text-sm inline-flex items-center gap-2 transition-colors"
                        >
                            <AlertTriangle class="w-4 h-4 text-[#D99A27]" />
                            <span>Incidencias</span>
                        </Link>

                        <button
                            v-if="canRecordInitialCount && !inventoryState.initial_count_pending"
                            type="button"
                            @click="openRecountModal"
                            class="h-10 px-4 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white font-bold text-xs sm:text-sm inline-flex items-center gap-2 shadow-sm transition-colors cursor-pointer"
                        >
                            <Pencil class="w-4 h-4" />
                            <span>Ajustar / Reiniciar</span>
                        </button>
                    </div>
                </header>

                <!-- Alertas globales -->
                <div
                    v-if="Object.keys(page.props.errors || {}).length > 0"
                    class="p-4 rounded-2xl bg-[#FCECEF] border border-[#E82147]/25 text-[#9F1239] flex items-center gap-3"
                    role="alert"
                >
                    <AlertTriangle class="w-5 h-5 text-[#E82147] shrink-0" />
                    <div class="text-xs sm:text-sm font-semibold">
                        <p v-for="(err, key) in page.props.errors" :key="key">{{ err }}</p>
                    </div>
                </div>

                <!-- 2. 3 KPIS DE EXISTENCIAS -->
                <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Total Cambios en circuito</p>
                            <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-[#1E1545] tabular-nums">
                                {{ inventoryState.balances.circuit_active_total ?? inventoryState.balances.plant_active_exchange ?? 0 }}
                            </p>
                            <p class="text-xs text-[#747181] mt-2 tabular-nums">
                                Planta: <strong class="text-[#1E1545]">{{ inventoryState.balances.plant_active_exchange ?? 0 }}</strong> ·
                                Camión: <strong class="text-[#1E1545]">{{ inventoryState.balances.truck_exchange_total ?? 0 }}</strong>
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center shrink-0">
                            <RefreshCw class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Llenos listos en Planta</p>
                            <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-[#26845B] tabular-nums">
                                {{ inventoryState.balances.exchange_full_available }}
                            </p>
                            <p class="text-xs text-[#747181] mt-2 tabular-nums">
                                Vacíos disponibles: <strong class="text-[#1E1545]">{{ inventoryState.balances.exchange_empty_available }}</strong>
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-[#E6F7EE] text-[#26845B] flex items-center justify-center shrink-0">
                            <Factory class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Fijos en custodia</p>
                            <p class="mt-1.5 text-2xl sm:text-3xl font-bold text-[#1E1545] tabular-nums">
                                {{ inventoryState.fixed_in_plant_by_customer.length }}
                                <span class="text-xs font-semibold text-[#747181]">pedidos</span>
                            </p>
                            <p class="text-xs text-[#747181] mt-2 tabular-nums">
                                Fijos dañados: <strong class="text-[#1E1545]">{{ inventoryState.balances.fixed_damaged_total }}</strong>
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                            <Tag class="w-5 h-5" />
                        </div>
                    </div>
                </section>

                <!-- 3. CONTEO INICIAL PENDIENTE -->
                <section
                    v-if="inventoryState.initial_count_pending"
                    class="rounded-2xl border border-[#D99A27]/35 bg-[#FEF3C7]/45 p-5 sm:p-6 space-y-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#D99A27] text-white flex items-center justify-center shrink-0">
                            <ClipboardCheck class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-[#1E1545]">Conteo físico inicial pendiente</h2>
                            <p class="text-xs text-[#747181] mt-0.5">
                                Captura cuántos botellones de cambio vacíos y llenos hay hoy en Planta para iniciar.
                            </p>
                        </div>
                    </div>

                    <form v-if="canRecordInitialCount" @submit.prevent="openInitialCountConfirmation" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Vacíos en Planta *</label>
                            <input v-model.number="initialCountForm.empty_count" type="number" min="0" required class="w-full h-11 rounded-xl border-[#E9E7F0] bg-white font-bold text-[#1E1545]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Llenos en Planta *</label>
                            <input v-model.number="initialCountForm.full_count" type="number" min="0" required class="w-full h-11 rounded-xl border-[#E9E7F0] bg-white font-bold text-[#1E1545]" />
                        </div>
                        <button
                            type="submit"
                            :disabled="initialCountForm.processing"
                            class="h-11 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer"
                        >
                            Registrar conteo inicial
                        </button>
                    </form>
                </section>

                <!-- 4. PLANTA VS CAMIÓN -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-9 w-9 rounded-xl bg-[#E5F4FD] text-[#0071BA] flex items-center justify-center">
                                    <Factory class="w-4 h-4" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Cambios en Planta</h2>
                                    <p class="text-xs text-[#747181]">{{ inventoryState.balances.plant_active_exchange ?? 0 }} en almacén</p>
                                </div>
                            </div>
                            <button
                                v-if="canRecordInitialCount && !inventoryState.initial_count_pending"
                                type="button"
                                @click="openRecountModal"
                                class="text-xs font-bold text-[#0071BA] hover:text-[#1E1545] inline-flex items-center gap-1 cursor-pointer"
                            >
                                <Pencil class="w-3.5 h-3.5" />
                                <span>Ajustar</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-3.5">
                                <p class="text-[11px] font-semibold text-[#747181]">Vacíos disponibles</p>
                                <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ inventoryState.balances.exchange_empty_available }}</p>
                            </div>
                            <div class="rounded-xl bg-[#E6F7EE]/70 border border-[#26845B]/25 p-3.5">
                                <p class="text-[11px] font-semibold text-[#26845B]">Llenos listos</p>
                                <p class="text-2xl font-bold text-[#26845B] tabular-nums mt-1">{{ inventoryState.balances.exchange_full_available }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-9 w-9 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center">
                                    <Truck class="w-4 h-4" />
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-[#1E1545]">Cambios en Camión</h2>
                                    <p class="text-xs text-[#747181]">{{ inventoryState.balances.truck_exchange_total ?? 0 }} en unidad</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-center">
                            <div class="rounded-xl bg-[#E5F4FD]/60 border border-[#0071BA]/20 p-3.5">
                                <p class="text-[11px] font-semibold text-[#0071BA]">Llenos en camión</p>
                                <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ inventoryState.balances.truck_full_available ?? 0 }}</p>
                            </div>
                            <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-3.5">
                                <p class="text-[11px] font-semibold text-[#747181]">Vacíos recogidos</p>
                                <p class="text-2xl font-bold text-[#1E1545] tabular-nums mt-1">{{ inventoryState.balances.truck_empty_collected ?? 0 }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 5. FIJOS EN CUSTODIA DE PLANTA -->
                <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-[#1E1545]">Fijos en custodia de Planta</h2>
                            <p class="text-xs text-[#747181]">Botellones propiedad de clientes actualmente en planta</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-[#F4F3F8] text-xs font-bold text-[#1E1545] tabular-nums">
                            {{ inventoryState.fixed_in_plant_by_customer.length }} activos
                        </span>
                    </div>

                    <div v-if="inventoryState.fixed_in_plant_by_customer.length === 0" class="py-8 text-center text-xs text-[#747181]">
                        No hay botellones fijos de clientes bajo custodia en planta en este momento.
                    </div>

                    <div v-else class="divide-y divide-[#F4F3F8]">
                        <div
                            v-for="item in inventoryState.fixed_in_plant_by_customer"
                            :key="item.order_item_id"
                            class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-bold text-[#1E1545]">
                                        {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md bg-[#E5F4FD] text-[#0071BA] text-[11px] font-bold font-mono">
                                        {{ item.order_folio }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-[#747181] tabular-nums">
                                    <span>Recibidos: <strong class="text-[#1E1545]">{{ item.received_at_plant }}</strong></span>
                                    <span>Por llenar: <strong class="text-[#B45309]">{{ item.in_washing }}</strong></span>
                                    <span>Listos: <strong class="text-[#26845B]">{{ item.ready_full }}</strong></span>
                                    <span v-if="item.damaged > 0" class="text-[#E82147] font-bold">Dañados: {{ item.damaged }}</span>
                                </div>
                            </div>

                            <button
                                v-if="canReportIncident"
                                type="button"
                                @click="openFixedIncidentModal(item)"
                                class="h-9 px-3 rounded-xl bg-[#FCECEF] hover:bg-[#F9D6DE] text-[#E82147] text-xs font-bold inline-flex items-center gap-1.5 cursor-pointer self-start sm:self-auto"
                            >
                                <AlertTriangle class="w-3.5 h-3.5" />
                                <span>Reportar daño</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- MODAL: CONFIRMAR CONTEO INICIAL -->
        <div v-if="showInitialCountConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <h3 class="text-base font-bold text-[#1E1545]">Confirmar conteo inicial</h3>
                <div class="rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] p-4 text-xs space-y-1.5 tabular-nums">
                    <div class="flex justify-between"><span>Vacíos:</span><strong class="text-[#1E1545]">{{ initialCountForm.empty_count }} u.</strong></div>
                    <div class="flex justify-between"><span>Llenos:</span><strong class="text-[#1E1545]">{{ initialCountForm.full_count }} u.</strong></div>
                    <div class="pt-1.5 border-t border-[#E9E7F0] flex justify-between font-bold text-[#1E1545]">
                        <span>Total en Planta:</span>
                        <span>{{ Number(initialCountForm.empty_count) + Number(initialCountForm.full_count) }} u.</span>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showInitialCountConfirmModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Volver</button>
                    <button type="button" @click="submitInitialCount" :disabled="initialCountForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] text-white text-xs font-bold cursor-pointer">Confirmar</button>
                </div>
            </div>
        </div>

        <!-- MODAL: AJUSTAR / REINICIAR CONTEO (PLANTA Y CAMIÓN) -->
        <div v-if="showRecountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs" @click.self="showRecountModal = false">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Ajustar o reiniciar conteo</h3>
                        <p class="text-xs text-[#747181]">Sube, baja o reinicia existencias en Planta y Camión</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="resetRecountToZero" class="px-2.5 py-1 rounded-lg bg-[#FCECEF] hover:bg-[#F9D6DE] text-[#E82147] text-[11px] font-bold cursor-pointer">Poner en 0</button>
                        <button type="button" @click="showRecountModal = false" class="text-[#747181] hover:text-[#1E1545] cursor-pointer"><X class="w-4 h-4" /></button>
                    </div>
                </div>

                <form @submit.prevent="submitRecount" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2">
                            <p class="text-[11px] font-bold uppercase text-[#0071BA]">En Planta</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.empty_count" type="number" min="0" max="10000" required class="w-full h-10 rounded-xl border-[#E9E7F0] font-bold text-[#1E1545]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.full_count" type="number" min="0" max="10000" required class="w-full h-10 rounded-xl border-[#E9E7F0] font-bold text-[#1E1545]" />
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2">
                            <p class="text-[11px] font-bold uppercase text-[#1E1545]">En Camión</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.truck_full_count" type="number" min="0" max="10000" required class="w-full h-10 rounded-xl border-[#E9E7F0] font-bold text-[#1E1545]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.truck_empty_count" type="number" min="0" max="10000" required class="w-full h-10 rounded-xl border-[#E9E7F0] font-bold text-[#1E1545]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] p-3 text-xs space-y-1 tabular-nums">
                        <div class="flex justify-between text-[#747181]"><span>Planta:</span><strong class="text-[#1E1545]">{{ recountPlantTotal }} u.</strong></div>
                        <div class="flex justify-between text-[#747181]"><span>Camión:</span><strong class="text-[#1E1545]">{{ recountTruckTotal }} u.</strong></div>
                        <div class="pt-1 border-t border-[#E9E7F0] flex justify-between font-bold text-[#1E1545] text-sm">
                            <span>Total circuito:</span><span>{{ recountCircuitTotal }} u.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Motivo *</label>
                            <select v-model="recountForm.reason" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="Ajuste de inventario físico">Ajuste de inventario físico</option>
                                <option value="Reinicio total de conteo">Reinicio total de conteo</option>
                                <option value="Corrección de inventario">Corrección de inventario</option>
                                <option value="Otro ajuste">Otro ajuste</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Notas (opcional)</label>
                            <input v-model="recountForm.notes" type="text" placeholder="Ej: Conteo físico" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                        </div>
                    </div>

                    <label v-if="canEditTarget && recountCircuitTotal > 0" class="flex items-start gap-2.5 p-3 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] cursor-pointer">
                        <input v-model="recountForm.sync_target" type="checkbox" class="mt-0.5 rounded border-[#E9E7F0] text-[#0071BA]" />
                        <span class="text-xs text-[#292638]">
                            <strong class="block text-[#1E1545]">Actualizar también la Meta</strong>
                            Cambiar meta a {{ recountCircuitTotal }} u.
                        </span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showRecountModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="recountForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] text-white text-xs font-bold cursor-pointer">Guardar ajuste</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: REPORTAR DAÑO EN FIJO -->
        <div v-if="showFixedIncidentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs" @click.self="showFixedIncidentModal = false">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Reportar daño en Fijo</h3>
                        <p class="text-xs text-[#747181]">{{ fixedIncidentCustomerLabel }}</p>
                    </div>
                    <button type="button" @click="showFixedIncidentModal = false" class="text-[#747181] hover:text-[#1E1545] cursor-pointer"><X class="w-4 h-4" /></button>
                </div>

                <form @submit.prevent="submitFixedIncident" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Cantidad *</label>
                            <input v-model.number="incidentForm.quantity" type="number" min="1" required class="w-full h-10 text-sm font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Problema *</label>
                            <select v-model="incidentForm.damage_type" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="rotura">Roto / quebrado</option>
                                <option value="fisura">Fisura o fuga</option>
                                <option value="contaminacion">Contaminado</option>
                                <option value="deformacion">Deformado</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Descripción *</label>
                        <input v-model="incidentForm.notes" type="text" required placeholder="Ej: Llegó fisurado en base" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showFixedIncidentModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="incidentForm.processing" class="h-10 px-5 rounded-xl bg-[#E82147] text-white text-xs font-bold cursor-pointer">Registrar daño</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
