<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    state: {
        type: Object,
        required: true,
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

// Pestaña activa: 'all' | 'pending' | 'washing' | 'prepared'
const activeTab = ref('all');

// Modales de Fijos
const showStartFixedModal = ref(false);
const showFinishFixedModal = ref(false);
const showDamageFixedModal = ref(false);
const selectedFixedItem = ref(null);

// Modales de Lotes de Cambio
const showStartBatchModal = ref(false);
const showFinishBatchModal = ref(false);
const showDamageBatchModal = ref(false);
const selectedBatch = ref(null);

// Forms
const startFixedForm = useForm({
    order_item_id: null,
    quantity: 1,
});

const finishFixedForm = useForm({
    order_item_id: null,
    quantity: 1,
});

const damageFixedForm = useForm({
    order_item_id: null,
    quantity: 1,
    damage_type: 'fuga_cuerpo',
    notes: '',
});

const startBatchForm = useForm({
    quantity: 1,
    notes: '',
});

const finishBatchForm = useForm({
    batch_id: null,
    quantity: 1,
});

const damageBatchForm = useForm({
    batch_id: null,
    quantity: 1,
    damage_type: 'fuga_cuerpo',
    notes: '',
});

// Tipos de daño comunes
const damageTypes = [
    { value: 'fuga_cuerpo', label: 'Fuga en cuerpo / fisura' },
    { value: 'golpe_fondo', label: 'Golpe severo en fondo' },
    { value: 'cuello_roto', label: 'Cuello / boquilla rota' },
    { value: 'contaminacion_quimica', label: 'Contaminación química u olor' },
    { value: 'desgaste_extremo', label: 'Desgaste extremo no apto' },
];

// Abrir modales Fijos
const openStartFixed = (item) => {
    if (!props.isOperator) return;
    selectedFixedItem.value = item;
    startFixedForm.order_item_id = item.item_id;
    startFixedForm.quantity = item.pending_to_wash_quantity;
    showStartFixedModal.value = true;
};

const submitStartFixed = () => {
    startFixedForm.post(route('planta.production.fixed.start'), {
        onSuccess: () => {
            showStartFixedModal.value = false;
            selectedFixedItem.value = null;
        },
    });
};

const openFinishFixed = (item) => {
    if (!props.isOperator) return;
    selectedFixedItem.value = item;
    finishFixedForm.order_item_id = item.item_id;
    finishFixedForm.quantity = item.in_washing_quantity;
    showFinishFixedModal.value = true;
};

const submitFinishFixed = () => {
    finishFixedForm.post(route('planta.production.fixed.finish'), {
        onSuccess: () => {
            showFinishFixedModal.value = false;
            selectedFixedItem.value = null;
        },
    });
};

const openDamageFixed = (item) => {
    if (!props.isOperator) return;
    selectedFixedItem.value = item;
    damageFixedForm.order_item_id = item.item_id;
    damageFixedForm.quantity = 1;
    damageFixedForm.damage_type = 'fuga_cuerpo';
    damageFixedForm.notes = '';
    showDamageFixedModal.value = true;
};

const submitDamageFixed = () => {
    damageFixedForm.post(route('planta.production.fixed.damage'), {
        onSuccess: () => {
            showDamageFixedModal.value = false;
            selectedFixedItem.value = null;
        },
    });
};

// Abrir modales Lotes
const openStartBatch = () => {
    if (!props.isOperator) return;
    const max = props.state.plant_inventory.exchange_empty_available;
    startBatchForm.quantity = max > 0 ? Math.min(20, max) : 1;
    startBatchForm.notes = '';
    showStartBatchModal.value = true;
};

const submitStartBatch = () => {
    startBatchForm.post(route('planta.production.exchange.start'), {
        onSuccess: () => {
            showStartBatchModal.value = false;
        },
    });
};

const openFinishBatch = (batch) => {
    if (!props.isOperator) return;
    selectedBatch.value = batch;
    finishBatchForm.batch_id = batch.id;
    finishBatchForm.quantity = batch.in_process_quantity;
    showFinishBatchModal.value = true;
};

const submitFinishBatch = () => {
    finishBatchForm.post(route('planta.production.exchange.finish'), {
        onSuccess: () => {
            showFinishBatchModal.value = false;
            selectedBatch.value = null;
        },
    });
};

const openDamageBatch = (batch) => {
    if (!props.isOperator) return;
    selectedBatch.value = batch;
    damageBatchForm.batch_id = batch.id;
    damageBatchForm.quantity = 1;
    damageBatchForm.damage_type = 'fuga_cuerpo';
    damageBatchForm.notes = '';
    showDamageBatchModal.value = true;
};

const submitDamageBatch = () => {
    damageBatchForm.post(route('planta.production.exchange.damage'), {
        onSuccess: () => {
            showDamageBatchModal.value = false;
            selectedBatch.value = null;
        },
    });
};

// Totales calculados
const totalFixedPending = computed(() => {
    return props.state.fixed_pending_prepare.reduce((acc, i) => acc + i.pending_to_wash_quantity, 0);
});

const totalFixedInWashing = computed(() => {
    return props.state.fixed_in_washing.reduce((acc, i) => acc + i.in_washing_quantity, 0);
});

const totalFixedPrepared = computed(() => {
    return props.state.fixed_prepared.reduce((acc, i) => acc + i.ready_to_load_quantity, 0);
});

const totalExchangeInBatches = computed(() => {
    return props.state.exchange_batches_in_process.reduce((acc, b) => acc + b.in_process_quantity, 0);
});
</script>

<template>
    <Head title="Lavado y Llenado - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-soap text-cyan-600"></i>
                        Lavado y Llenado de Planta
                    </h2>
                    <p class="text-sm text-slate-500">Mesa de control de purificación, preparación de fijos y lotes de cambio</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="isOperator"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-cyan-50 text-cyan-800 border border-cyan-200"
                    >
                        <i class="fa-solid fa-user-gear mr-1.5 text-cyan-600"></i>
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
                        <strong>Modo Consulta y Supervisión:</strong> Estás visualizando la mesa de producción en tiempo real. Por regla de integridad de custodia física, el inicio y finalización de lavado corresponden exclusivamente al Operador de Planta.
                    </div>
                </div>

                <!-- RESUMEN EN CABECERA DE SALDOS OPERATIVOS -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white rounded-2xl p-4 border border-slate-200/90 shadow-2xs">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Vacíos Listos</div>
                        <div class="text-2xl font-extrabold text-slate-800 mt-1">
                            {{ state.plant_inventory.exchange_empty_available }}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Disponibles para lote</div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-cyan-200 bg-cyan-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-cyan-800 uppercase tracking-wider">En Lavado / Túnel</div>
                        <div class="text-2xl font-extrabold text-cyan-700 mt-1">
                            {{ state.plant_inventory.exchange_washing_filling + totalFixedInWashing }}
                        </div>
                        <div class="text-[11px] text-cyan-600 mt-0.5">
                            {{ state.plant_inventory.exchange_washing_filling }} cambio + {{ totalFixedInWashing }} fijos
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-emerald-200 bg-emerald-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Llenos Disponibles</div>
                        <div class="text-2xl font-extrabold text-emerald-700 mt-1">
                            {{ state.plant_inventory.exchange_full_available }}
                        </div>
                        <div class="text-[11px] text-emerald-600 mt-0.5">Cambio listos para reparto</div>
                    </div>

                    <div class="bg-white rounded-2xl p-4 border border-purple-200 bg-purple-50/20 shadow-2xs">
                        <div class="text-xs font-bold text-purple-800 uppercase tracking-wider">Fijos Preparados</div>
                        <div class="text-2xl font-extrabold text-purple-700 mt-1">
                            {{ totalFixedPrepared }}
                        </div>
                        <div class="text-[11px] text-purple-600 mt-0.5">Listos para devolución</div>
                    </div>
                </div>

                <!-- PESTAÑAS DE VISTA -->
                <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                    <button
                        type="button"
                        @click="activeTab = 'all'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'all'
                                ? 'bg-cyan-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-layer-group mr-1.5"></i>
                        Mesa Completa
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'pending'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'pending'
                                ? 'bg-cyan-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-clock mr-1.5 text-amber-500"></i>
                        1. Pendientes de Preparar ({{ totalFixedPending }} fijos | {{ state.plant_inventory.exchange_empty_available }} vacíos)
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'washing'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'washing'
                                ? 'bg-cyan-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-soap mr-1.5 text-cyan-500"></i>
                        2. En Lavado y Llenado ({{ totalFixedInWashing }} fijos | {{ totalExchangeInBatches }} cambio)
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'prepared'"
                        :class="[
                            'px-4 py-2 rounded-xl text-xs font-bold transition-colors whitespace-nowrap cursor-pointer',
                            activeTab === 'prepared'
                                ? 'bg-cyan-700 text-white shadow-2xs'
                                : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                        ]"
                    >
                        <i class="fa-solid fa-circle-check mr-1.5 text-emerald-500"></i>
                        3. Preparados y Listos ({{ totalFixedPrepared }} fijos | {{ state.plant_inventory.exchange_full_available }} cambio)
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- BLOQUE 1: PENDIENTES DE PREPARAR           -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'all' || activeTab === 'pending'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <span class="h-6 w-6 rounded-lg bg-amber-100 text-amber-800 text-xs flex items-center justify-center font-black">1</span>
                                Pendientes de Preparar
                            </h3>
                            <p class="text-xs text-slate-500">Botellones vacíos aptos en planta listos para ingresar a línea de lavado</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- 1.1 Fijos por Cliente pendientes de entrar a lavado -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center text-sm font-bold">
                                        <i class="fa-solid fa-user-tag"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800">Botellones Fijos por Propietario</h4>
                                        <p class="text-[11px] text-slate-400">Recibidos aptos aún no ingresados al túnel</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                    {{ totalFixedPending }} pendientes
                                </span>
                            </div>

                            <div v-if="state.fixed_pending_prepare.length === 0" class="text-center py-8 text-xs text-slate-400">
                                <i class="fa-solid fa-box-open text-2xl mb-2 text-slate-300 block"></i>
                                No hay botellones fijos en espera de lavado.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in state.fixed_pending_prepare"
                                    :key="item.item_id"
                                    class="border border-slate-200 rounded-xl p-3 hover:border-cyan-300 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-extrabold text-slate-800">
                                                {{ item.customer_nickname || item.customer_name }}
                                            </span>
                                            <span v-if="item.customer_code" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-semibold">
                                                {{ item.customer_code }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-500">
                                            <span>Pedido: <strong class="text-slate-700">{{ item.order_folio }}</strong></span>
                                            <span class="mx-1.5">•</span>
                                            <span>Recibidos aptos: <strong class="text-slate-700">{{ item.received_at_plant_quantity }}</strong></span>
                                            <span v-if="item.washing_quantity > 0" class="text-cyan-700"> ({{ item.washing_quantity }} en lavado)</span>
                                        </div>
                                        <div class="text-xs font-bold text-amber-700">
                                            {{ item.pending_to_wash_quantity }} unidad(es) listas para lavar
                                        </div>
                                    </div>

                                    <div>
                                        <button
                                            type="button"
                                            @click="openStartFixed(item)"
                                            :disabled="!isOperator"
                                            :class="[
                                                'px-3.5 py-2 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 transition-colors cursor-pointer',
                                                isOperator
                                                    ? 'bg-cyan-700 hover:bg-cyan-800 text-white shadow-2xs'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            ]"
                                            :title="!isOperator ? 'Solo el Operador de Planta puede iniciar el lavado' : 'Ingresar al túnel de lavado'"
                                        >
                                            <i class="fa-solid fa-soap text-cyan-200"></i>
                                            Iniciar Lavado
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 1.2 Botellones de Cambio: Existencias listas para crear lote -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center text-sm font-bold">
                                            <i class="fa-solid fa-boxes-stacked"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800">Fondo General de Cambio</h4>
                                            <p class="text-[11px] text-slate-400">Existencias físicas de vacíos aptos en planta</p>
                                        </div>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                                        {{ state.plant_inventory.exchange_empty_available }} disponibles
                                    </span>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2 mb-4">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-600 font-medium">Vacíos aptos en almacén:</span>
                                        <span class="font-extrabold text-slate-800 text-sm">{{ state.plant_inventory.exchange_empty_available }}</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        Para mantener el control físico estricto, la producción de botellones de cambio se realiza en 2 pasos: primero se inicia un lote foliado y al terminar se liberan como llenos disponibles. No se convierten directamente en un solo clic.
                                    </p>
                                </div>
                            </div>

                            <div>
                                <button
                                    type="button"
                                    @click="openStartBatch"
                                    :disabled="!isOperator || state.plant_inventory.exchange_empty_available <= 0"
                                    :class="[
                                        'w-full py-2.5 px-4 rounded-xl text-xs font-bold inline-flex items-center justify-center gap-2 transition-colors cursor-pointer',
                                        isOperator && state.plant_inventory.exchange_empty_available > 0
                                            ? 'bg-blue-700 hover:bg-blue-800 text-white shadow-2xs'
                                            : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                    ]"
                                    :title="!isOperator ? 'Solo el Operador de Planta puede iniciar lotes' : 'Abrir nuevo lote de producción'"
                                >
                                    <i class="fa-solid fa-plus-circle"></i>
                                    Iniciar Nuevo Lote de Lavado / Llenado
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- BLOQUE 2: EN LAVADO Y LLENADO              -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'all' || activeTab === 'washing'" class="space-y-4 pt-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <span class="h-6 w-6 rounded-lg bg-cyan-100 text-cyan-800 text-xs flex items-center justify-center font-black">2</span>
                                En Lavado y Llenado (Túnel y Purificación)
                            </h3>
                            <p class="text-xs text-slate-500">Unidades en proceso físico en planta. Puedes finalizar cuando estén listos o reportar daños</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- 2.1 Fijos en Túnel de Lavado -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-cyan-50 text-cyan-700 flex items-center justify-center text-sm font-bold">
                                        <i class="fa-solid fa-water"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800">Fijos en Proceso</h4>
                                        <p class="text-[11px] text-slate-400">Por cliente y pedido en la línea de purificación</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800">
                                    {{ totalFixedInWashing }} en túnel
                                </span>
                            </div>

                            <div v-if="state.fixed_in_washing.length === 0" class="text-center py-8 text-xs text-slate-400">
                                <i class="fa-solid fa-soap text-2xl mb-2 text-slate-300 block"></i>
                                No hay fijos actualmente en proceso de lavado.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in state.fixed_in_washing"
                                    :key="item.item_id"
                                    class="border border-cyan-200 rounded-xl p-3 bg-cyan-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-extrabold text-slate-800">
                                                {{ item.customer_nickname || item.customer_name }}
                                            </span>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-cyan-200 text-cyan-800 font-bold">
                                                {{ item.order_folio }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600">
                                            <span>En túnel: <strong class="text-cyan-800 text-xs">{{ item.in_washing_quantity }}</strong> u.</span>
                                            <span class="mx-1">•</span>
                                            <span>Terminados previos: {{ item.prepared_quantity }}</span>
                                            <span v-if="item.damaged_quantity > 0" class="text-rose-600 ml-1">({{ item.damaged_quantity }} dañados)</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <button
                                            type="button"
                                            @click="openDamageFixed(item)"
                                            :disabled="!isOperator"
                                            :class="[
                                                'px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer',
                                                isOperator
                                                    ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            ]"
                                            title="Reportar envase roto o con fuga en lavado"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                            Dañado
                                        </button>

                                        <button
                                            type="button"
                                            @click="openFinishFixed(item)"
                                            :disabled="!isOperator"
                                            :class="[
                                                'px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer',
                                                isOperator
                                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            ]"
                                            title="Finalizar preparación y marcar listo para reparto"
                                        >
                                            <i class="fa-solid fa-check mr-1"></i>
                                            Finalizar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2.2 Lotes de Cambio en Proceso -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-cyan-50 text-cyan-700 flex items-center justify-center text-sm font-bold">
                                        <i class="fa-solid fa-diagram-project"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800">Lotes de Cambio Abiertos</h4>
                                        <p class="text-[11px] text-slate-400">Trazabilidad por lote con fecha, responsable y avance</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-100 text-cyan-800">
                                    {{ state.exchange_batches_in_process.length }} activos
                                </span>
                            </div>

                            <div v-if="state.exchange_batches_in_process.length === 0" class="text-center py-8 text-xs text-slate-400">
                                <i class="fa-solid fa-clipboard-list text-2xl mb-2 text-slate-300 block"></i>
                                No hay lotes de cambio en proceso en este momento.
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="batch in state.exchange_batches_in_process"
                                    :key="batch.id"
                                    class="border border-cyan-200 rounded-xl p-3 bg-cyan-50/20 space-y-2"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-cyan-100 text-cyan-900 border border-cyan-300">
                                                {{ batch.batch_number }}
                                            </span>
                                            <span class="text-[11px] text-slate-500 font-medium">
                                                Iniciado por {{ batch.operator_name }} ({{ batch.started_at }})
                                            </span>
                                        </div>
                                        <span class="text-xs font-extrabold text-cyan-800">
                                            {{ batch.in_process_quantity }} pendientes
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between text-[11px] text-slate-600 bg-white/70 p-2 rounded-lg border border-slate-200/60">
                                        <div>Inicial: <strong>{{ batch.initial_quantity }}</strong></div>
                                        <div class="text-emerald-700 font-semibold">Terminados: <strong>{{ batch.finished_quantity }}</strong></div>
                                        <div class="text-rose-600 font-semibold">Dañados: <strong>{{ batch.damaged_quantity }}</strong></div>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        <button
                                            type="button"
                                            @click="openDamageBatch(batch)"
                                            :disabled="!isOperator"
                                            :class="[
                                                'px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer',
                                                isOperator
                                                    ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            ]"
                                            title="Reportar rotura o fuga en este lote"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                            Dañado
                                        </button>

                                        <button
                                            type="button"
                                            @click="openFinishBatch(batch)"
                                            :disabled="!isOperator"
                                            :class="[
                                                'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer',
                                                isOperator
                                                    ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs'
                                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                            ]"
                                            title="Finalizar botellones y sumarlos a llenos disponibles"
                                        >
                                            <i class="fa-solid fa-check mr-1"></i>
                                            Finalizar Botellones
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- BLOQUE 3: PREPARADOS (LISTOS PARA REPARTO)  -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'all' || activeTab === 'prepared'" class="space-y-4 pt-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <span class="h-6 w-6 rounded-lg bg-emerald-100 text-emerald-800 text-xs flex items-center justify-center font-black">3</span>
                                Preparados y Listos para Reparto
                            </h3>
                            <p class="text-xs text-slate-500">Resultado final disponible en planta para armar cargas al camión</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- 3.1 Fijos preparados de Clientes -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800">Fijos Preparados</h4>
                                        <p class="text-[11px] text-slate-400">Listos para devolución a su cliente respectivo</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                    {{ totalFixedPrepared }} listos
                                </span>
                            </div>

                            <div v-if="state.fixed_prepared.length === 0" class="text-center py-8 text-xs text-slate-400">
                                <i class="fa-solid fa-clipboard-check text-2xl mb-2 text-slate-300 block"></i>
                                No hay fijos preparados pendientes de cargar al camión.
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in state.fixed_prepared"
                                    :key="item.item_id"
                                    class="border border-emerald-200 rounded-xl p-3 bg-emerald-50/20 flex items-center justify-between gap-3"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-extrabold text-slate-800">
                                                {{ item.customer_nickname || item.customer_name }}
                                            </span>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">
                                                {{ item.order_folio }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            Preparados: <strong class="text-emerald-800">{{ item.prepared_quantity }}</strong>
                                            <span v-if="item.loaded_quantity > 0"> ({{ item.loaded_quantity }} ya en camión)</span>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-600 text-white">
                                            {{ item.ready_to_load_quantity }} listos
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3.2 Llenos disponibles de Cambio & Lotes recientes -->
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-sm font-bold">
                                        <i class="fa-solid fa-bottle-water"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800">Llenos Disponibles de Cambio</h4>
                                        <p class="text-[11px] text-slate-400">Stock general listo para transferir al camión</p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm font-extrabold bg-emerald-100 text-emerald-800">
                                    {{ state.plant_inventory.exchange_full_available }} garrafones
                                </span>
                            </div>

                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600 space-y-1">
                                <div class="flex justify-between">
                                    <span>Llenos disponibles para entrega:</span>
                                    <strong class="text-slate-800">{{ state.plant_inventory.exchange_full_available }}</strong>
                                </div>
                                <div class="flex justify-between text-amber-700">
                                    <span>Llenos reservados en propuestas activas:</span>
                                    <strong>{{ state.plant_inventory.exchange_full_reserved }}</strong>
                                </div>
                            </div>

                            <!-- Historial de lotes recientes -->
                            <div>
                                <h5 class="text-xs font-bold text-slate-700 uppercase mb-2">Últimos Lotes de Producción</h5>
                                <div v-if="state.recent_batches.length === 0" class="text-xs text-slate-400 py-2">
                                    Aún no hay lotes registrados.
                                </div>
                                <div v-else class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    <div
                                        v-for="rb in state.recent_batches"
                                        :key="rb.id"
                                        class="flex items-center justify-between text-[11px] p-2 rounded-lg bg-white border border-slate-200"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-slate-700">{{ rb.batch_number }}</span>
                                            <span
                                                :class="[
                                                    'px-1.5 py-0.2 rounded text-[10px] font-semibold',
                                                    rb.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-cyan-100 text-cyan-800'
                                                ]"
                                            >
                                                {{ rb.status === 'completed' ? 'Finalizado' : 'En proceso' }}
                                            </span>
                                        </div>
                                        <div class="text-slate-500">
                                            {{ rb.finished_quantity }} / {{ rb.initial_quantity }} u.
                                            <span v-if="rb.damaged_quantity > 0" class="text-rose-600 font-semibold"> ({{ rb.damaged_quantity }} dañados)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: INICIAR LAVADO DE FIJOS             -->
        <!-- ========================================== -->
        <div v-if="showStartFixedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-soap text-cyan-600"></i>
                        Ingresar Fijos a Lavado
                    </h3>
                    <button @click="showStartFixedModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedFixedItem" class="text-xs space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>Cliente: <strong>{{ selectedFixedItem.customer_nickname || selectedFixedItem.customer_name }}</strong></div>
                    <div>Pedido: <strong>{{ selectedFixedItem.order_folio }}</strong></div>
                    <div>Pendientes por ingresar: <strong class="text-amber-700">{{ selectedFixedItem.pending_to_wash_quantity }}</strong></div>
                </div>

                <form @submit.prevent="submitStartFixed" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad a ingresar al túnel
                        </label>
                        <input
                            v-model.number="startFixedForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedFixedItem ? selectedFixedItem.pending_to_wash_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500"
                        />
                        <span v-if="startFixedForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ startFixedForm.errors.quantity }}
                        </span>
                        <span v-if="startFixedForm.errors.fixed" class="text-xs text-rose-600 mt-1 block">
                            {{ startFixedForm.errors.fixed }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showStartFixedModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="startFixedForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-cyan-700 hover:bg-cyan-800 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Confirmar Ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: FINALIZAR PREPARACIÓN DE FIJOS       -->
        <!-- ========================================== -->
        <div v-if="showFinishFixedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        Finalizar Preparación de Fijos
                    </h3>
                    <button @click="showFinishFixedModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedFixedItem" class="text-xs space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>Cliente: <strong>{{ selectedFixedItem.customer_nickname || selectedFixedItem.customer_name }}</strong></div>
                    <div>Pedido: <strong>{{ selectedFixedItem.order_folio }}</strong></div>
                    <div>En lavado y llenado: <strong class="text-cyan-800">{{ selectedFixedItem.in_washing_quantity }}</strong></div>
                </div>

                <form @submit.prevent="submitFinishFixed" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad purificada y sellada (lista)
                        </label>
                        <input
                            v-model.number="finishFixedForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedFixedItem ? selectedFixedItem.in_washing_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        <span v-if="finishFixedForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ finishFixedForm.errors.quantity }}
                        </span>
                        <span v-if="finishFixedForm.errors.fixed" class="text-xs text-rose-600 mt-1 block">
                            {{ finishFixedForm.errors.fixed }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showFinishFixedModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="finishFixedForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Finalizar Preparación
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: REPORTAR DAÑO EN FIJOS (LAVADO)     -->
        <!-- ========================================== -->
        <div v-if="showDamageFixedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-rose-700 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Reportar Envase Fijo Dañado
                    </h3>
                    <button @click="showDamageFixedModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedFixedItem" class="text-xs space-y-1 bg-rose-50 p-3 rounded-xl border border-rose-200 text-rose-900">
                    <div>Cliente: <strong>{{ selectedFixedItem.customer_nickname || selectedFixedItem.customer_name }}</strong></div>
                    <div>Pedido: <strong>{{ selectedFixedItem.order_folio }}</strong></div>
                    <div>En lavado: <strong>{{ selectedFixedItem.in_washing_quantity }}</strong></div>
                    <p class="text-[11px] text-rose-700 mt-1">
                        Se registrará una incidencia pendiente de resolución. La unidad se separará del proceso y no se marcará como devuelta ni se cobrará rellenado.
                    </p>
                </div>

                <form @submit.prevent="submitDamageFixed" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad dañada
                        </label>
                        <input
                            v-model.number="damageFixedForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedFixedItem ? selectedFixedItem.in_washing_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Tipo de daño
                        </label>
                        <select
                            v-model="damageFixedForm.damage_type"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                        >
                            <option v-for="dt in damageTypes" :key="dt.value" :value="dt.value">
                                {{ dt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Notas u observaciones
                        </label>
                        <textarea
                            v-model="damageFixedForm.notes"
                            rows="2"
                            placeholder="Detalle de rotura o fisura detectada..."
                            class="w-full rounded-xl border-slate-300 text-xs focus:border-rose-500 focus:ring-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showDamageFixedModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="damageFixedForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Registrar Incidencia
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: INICIAR LOTE DE CAMBIO              -->
        <!-- ========================================== -->
        <div v-if="showStartBatchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-blue-600"></i>
                        Iniciar Lote de Botellones de Cambio
                    </h3>
                    <button @click="showStartBatchModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="text-xs bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                    <div>Vacíos disponibles en planta: <strong>{{ state.plant_inventory.exchange_empty_available }}</strong></div>
                    <p class="text-[11px] text-slate-500">
                        Se descontará de vacíos disponibles y se agregará al saldo en lavado/llenado con un folio único de lote.
                    </p>
                </div>

                <form @submit.prevent="submitStartBatch" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad a ingresar al lote
                        </label>
                        <input
                            v-model.number="startBatchForm.quantity"
                            type="number"
                            min="1"
                            :max="state.plant_inventory.exchange_empty_available"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <span v-if="startBatchForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ startBatchForm.errors.quantity }}
                        </span>
                        <span v-if="startBatchForm.errors.batch" class="text-xs text-rose-600 mt-1 block">
                            {{ startBatchForm.errors.batch }}
                        </span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Notas del lote (opcional)
                        </label>
                        <input
                            v-model="startBatchForm.notes"
                            type="text"
                            placeholder="Ej: Turno matutino / garrafones descargados hoy"
                            class="w-full rounded-xl border-slate-300 text-xs focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showStartBatchModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="startBatchForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-700 hover:bg-blue-800 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Abrir Lote
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: FINALIZAR LOTE DE CAMBIO            -->
        <!-- ========================================== -->
        <div v-if="showFinishBatchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        Finalizar Botellones del Lote
                    </h3>
                    <button @click="showFinishBatchModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedBatch" class="text-xs bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                    <div>Lote: <strong class="font-mono">{{ selectedBatch.batch_number }}</strong></div>
                    <div>Pendientes en proceso: <strong class="text-cyan-800">{{ selectedBatch.in_process_quantity }}</strong></div>
                    <p class="text-[11px] text-slate-500">
                        Los botellones finalizados se sumarán inmediatamente a las existencias de llenos disponibles en Planta.
                    </p>
                </div>

                <form @submit.prevent="submitFinishBatch" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad purificada y sellada (lista)
                        </label>
                        <input
                            v-model.number="finishBatchForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedBatch ? selectedBatch.in_process_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                        <span v-if="finishBatchForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                            {{ finishBatchForm.errors.quantity }}
                        </span>
                        <span v-if="finishBatchForm.errors.batch" class="text-xs text-rose-600 mt-1 block">
                            {{ finishBatchForm.errors.batch }}
                        </span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showFinishBatchModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="finishBatchForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Confirmar Llenos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL: REPORTAR DAÑO EN LOTE DE CAMBIO     -->
        <!-- ========================================== -->
        <div v-if="showDamageBatchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-rose-700 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Reportar Envase Dañado en Lote
                    </h3>
                    <button @click="showDamageBatchModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div v-if="selectedBatch" class="text-xs bg-rose-50 p-3 rounded-xl border border-rose-200 text-rose-900 space-y-1">
                    <div>Lote: <strong class="font-mono">{{ selectedBatch.batch_number }}</strong></div>
                    <div>Pendientes en proceso: <strong>{{ selectedBatch.in_process_quantity }}</strong></div>
                    <p class="text-[11px] text-rose-700 mt-1">
                        Se registrará una incidencia pendiente de resolución. La unidad se descontará del proceso y no entrará a llenos disponibles.
                    </p>
                </div>

                <form @submit.prevent="submitDamageBatch" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad dañada
                        </label>
                        <input
                            v-model.number="damageBatchForm.quantity"
                            type="number"
                            min="1"
                            :max="selectedBatch ? selectedBatch.in_process_quantity : 1"
                            required
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Tipo de daño
                        </label>
                        <select
                            v-model="damageBatchForm.damage_type"
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500"
                        >
                            <option v-for="dt in damageTypes" :key="dt.value" :value="dt.value">
                                {{ dt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Notas u observaciones
                        </label>
                        <textarea
                            v-model="damageBatchForm.notes"
                            rows="2"
                            placeholder="Detalle del daño detectado durante lavado o llenado..."
                            class="w-full rounded-xl border-slate-300 text-xs focus:border-rose-500 focus:ring-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showDamageBatchModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="damageBatchForm.processing"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-2xs transition-colors cursor-pointer"
                        >
                            Registrar Daño
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

