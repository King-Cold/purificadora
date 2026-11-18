<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { alertWarning, confirmAction } from '@/utils/swal';
import {
    AlertTriangle,
    Check,
    CheckCheck,
    Droplets,
    Factory,
    PackageCheck,
    SlidersHorizontal,
    Sparkles,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    activeTab: {
        type: String,
        default: 'por-recibir',
    },
    fixedPending: {
        type: Array,
        default: () => [],
    },
    fixedPendingCount: {
        type: Number,
        default: 0,
    },
    fixedToFill: {
        type: Array,
        default: () => [],
    },
    fixedToFillCount: {
        type: Number,
        default: 0,
    },
    isOperator: {
        type: Boolean,
        default: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const formError = computed(() => page.props.errors?.fill || page.props.errors?.received_quantity || null);

const currentTab = ref(props.activeTab || 'por-recibir');

const setTab = (tab) => {
    currentTab.value = tab;
    router.get(route('planta.fijos.index'), { tab }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const totalPendingBottles = computed(() =>
    props.fixedPending.reduce((acc, curr) => acc + (curr.expected_quantity || 0), 0)
);
const totalFillBottles = computed(() =>
    props.fixedToFill.reduce((acc, curr) => acc + (curr.pending_quantity || 0), 0)
);

/* =========================================================================
   PESTAÑA 1: POR RECIBIR (1 toque directo: Recibir y llenar | Solo recibir)
   ========================================================================= */
const submittingItem = ref({});
const batchProcessing = ref(false);

const handleReceiveAndFillSingle = (item) => {
    submittingItem.value[item.order_item_id] = 'both';
    router.post(
        route('planta.fijos.receive-and-fill'),
        { order_item_id: item.order_item_id },
        {
            preserveScroll: true,
            onFinish: () => {
                submittingItem.value[item.order_item_id] = null;
            },
        }
    );
};

const handleReceiveOnlySingle = (item) => {
    submittingItem.value[item.order_item_id] = 'receive';
    router.post(
        route('planta.reception.confirm-fixed'),
        {
            order_item_id: item.order_item_id,
            received_quantity: item.expected_quantity,
            damaged_quantity: 0,
            difference_reason: null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                submittingItem.value[item.order_item_id] = null;
            },
        }
    );
};

const eligibleForBatch = computed(() =>
    props.fixedPending.filter((item) => (item.damaged_quantity || 0) === 0)
);
const eligibleBottlesCount = computed(() =>
    eligibleForBatch.value.reduce((acc, curr) => acc + (curr.expected_quantity || 0), 0)
);

const handleReceiveAndFillAll = async () => {
    if (eligibleForBatch.value.length === 0 || batchProcessing.value) return;

    const result = await confirmAction({
        title: '¿Recibir y dejar llenos todos?',
        text: `Se confirmarán ${eligibleBottlesCount.value} botellón(es) fijos de ${eligibleForBatch.value.length} pedido(s) y quedarán listos en Reparto.`,
        confirmText: 'Sí, recibir y llenar',
        tone: 'primary',
    });

    if (!result.isConfirmed) return;

    batchProcessing.value = true;
    router.post(route('planta.fijos.receive-and-fill-all'), {}, {
        preserveScroll: true,
        onFinish: () => {
            batchProcessing.value = false;
        },
    });
};

const handleReceiveOnlyAll = async () => {
    if (eligibleForBatch.value.length === 0 || batchProcessing.value) return;

    const result = await confirmAction({
        title: '¿Solo recibir todos en Planta?',
        text: `Se recibirán ${eligibleBottlesCount.value} botellón(es) y pasarán a la lista "Por llenar".`,
        confirmText: 'Sí, recibir todos',
        tone: 'primary',
    });

    if (!result.isConfirmed) return;

    batchProcessing.value = true;
    router.post(route('planta.reception.confirm-all-fixed'), {}, {
        preserveScroll: true,
        onFinish: () => {
            batchProcessing.value = false;
        },
    });
};

// Incidencia en recepción (faltantes / daños)
const showReceptionIncidentModal = ref(false);
const itemForReceptionIncident = ref(null);
const receptionIncidentForm = useForm({
    order_item_id: null,
    received_quantity: 0,
    damaged_quantity: 0,
    damage_type: 'rotura',
    damage_notes: '',
    difference_reason: '',
});

const openReceptionIncidentModal = (item) => {
    itemForReceptionIncident.value = item;
    receptionIncidentForm.order_item_id = item.order_item_id;
    receptionIncidentForm.received_quantity = item.expected_quantity;
    receptionIncidentForm.damaged_quantity = 0;
    receptionIncidentForm.damage_type = 'rotura';
    receptionIncidentForm.damage_notes = '';
    receptionIncidentForm.difference_reason = '';
    receptionIncidentForm.clearErrors();
    showReceptionIncidentModal.value = true;
};

const closeReceptionIncidentModal = () => {
    showReceptionIncidentModal.value = false;
    itemForReceptionIncident.value = null;
};

const receptionShortage = computed(() => {
    if (!itemForReceptionIncident.value) return 0;
    const total = Number(receptionIncidentForm.received_quantity || 0) + Number(receptionIncidentForm.damaged_quantity || 0);
    return Math.max(0, itemForReceptionIncident.value.expected_quantity - total);
});

const submitReceptionIncident = () => {
    if (!itemForReceptionIncident.value || receptionIncidentForm.processing) return;

    if (receptionShortage.value > 0 && receptionIncidentForm.difference_reason.trim().length < 5) {
        alertWarning('Indica el motivo de los botellones faltantes (mínimo 5 caracteres).');
        return;
    }

    if (receptionIncidentForm.damaged_quantity > 0 && receptionIncidentForm.damage_notes.trim().length < 5) {
        alertWarning('Describe brevemente el daño detectado (mínimo 5 caracteres).');
        return;
    }

    receptionIncidentForm.post(route('planta.reception.confirm-fixed'), {
        preserveScroll: true,
        onSuccess: () => closeReceptionIncidentModal(),
    });
};

/* =========================================================================
   PESTAÑA 2: POR LLENAR (1 toque directo + modal opcional solo si es parcial)
   ========================================================================= */
const handleFillSingleDirect = (item) => {
    submittingItem.value[item.order_item_id] = 'fill';
    router.post(
        route('planta.fijos.mark-filled'),
        {
            order_item_id: item.order_item_id,
            quantity: item.pending_quantity,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                submittingItem.value[item.order_item_id] = null;
            },
        }
    );
};

const handleFillAllDirect = async () => {
    if (props.fixedToFill.length === 0 || batchProcessing.value) return;

    const result = await confirmAction({
        title: '¿Marcar todos como llenos?',
        text: `Los ${totalFillBottles.value} botellón(es) de ${props.fixedToFill.length} pedido(s) quedarán listos en Reparto.`,
        confirmText: 'Sí, llenar todos',
        tone: 'primary',
    });

    if (!result.isConfirmed) return;

    batchProcessing.value = true;
    router.post(route('planta.fijos.mark-all-filled'), {}, {
        preserveScroll: true,
        onFinish: () => {
            batchProcessing.value = false;
        },
    });
};

// Modal parcial (solo si no se llenan todos)
const showFillModal = ref(false);
const itemToFill = ref(null);
const fillForm = useForm({
    order_item_id: null,
    quantity: 1,
});

const openFillModal = (item) => {
    itemToFill.value = item;
    fillForm.order_item_id = item.order_item_id;
    fillForm.quantity = item.pending_quantity;
    fillForm.clearErrors();
    showFillModal.value = true;
};

const closeFillModal = () => {
    showFillModal.value = false;
    itemToFill.value = null;
};

const incrementFillQty = () => {
    if (itemToFill.value && fillForm.quantity < itemToFill.value.pending_quantity) {
        fillForm.quantity++;
    }
};

const decrementFillQty = () => {
    if (fillForm.quantity > 1) {
        fillForm.quantity--;
    }
};

const submitFill = () => {
    if (!itemToFill.value || fillForm.processing) return;

    fillForm.post(route('planta.fijos.mark-filled'), {
        preserveScroll: true,
        onSuccess: () => closeFillModal(),
    });
};

// Incidencia durante lavado o llenado
const showFillIncidentModal = ref(false);
const itemForFillIncident = ref(null);
const fillIncidentForm = useForm({
    order_item_id: null,
    quantity: 1,
    damage_type: 'rotura',
    notes: '',
});

const openFillIncidentModal = (item) => {
    itemForFillIncident.value = item;
    fillIncidentForm.order_item_id = item.order_item_id;
    fillIncidentForm.quantity = 1;
    fillIncidentForm.damage_type = 'rotura';
    fillIncidentForm.notes = '';
    fillIncidentForm.clearErrors();
    showFillIncidentModal.value = true;
};

const closeFillIncidentModal = () => {
    showFillIncidentModal.value = false;
    itemForFillIncident.value = null;
};

const submitFillIncident = () => {
    if (!itemForFillIncident.value || fillIncidentForm.processing) return;

    if (fillIncidentForm.notes.trim().length < 5) {
        alertWarning('Describe brevemente el daño detectado (mínimo 5 caracteres).');
        return;
    }

    fillIncidentForm.post(route('planta.fijos.report-incident'), {
        preserveScroll: true,
        onSuccess: () => closeFillIncidentModal(),
    });
};
</script>

<template>
    <Head title="Fijos planta" />

    <AuthenticatedLayout wide>
        <div class="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-24 md:pb-12">

            <div
                v-if="formError"
                class="p-3.5 rounded-2xl bg-[#FCECEF] border border-[#F6C8D2] text-[#B91C3C] text-xs sm:text-sm flex items-start gap-2.5"
            >
                <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
                <div class="font-semibold">{{ formError }}</div>
            </div>

            <!-- Encabezado y navegación de pestañas -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_2px_rgba(30,21,69,0.04)] p-4 sm:p-5 space-y-3.5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                            <Factory class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold text-[#292638] tracking-tight">
                                Fijos en Planta
                            </h1>
                            <p class="text-xs text-[#747181]">
                                Recibe los envases del repartidor y déjalos llenos en un toque.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2 pestañas claras -->
                <div class="flex items-center gap-2 border-t border-[#E9E7F0] pt-3">
                    <button
                        @click="setTab('por-recibir')"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition min-h-[42px] inline-flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por-recibir'
                            ? 'bg-[#1E1545] text-white border-[#1E1545]'
                            : 'bg-[#F4F3F8] text-[#292638] border-[#E9E7F0] hover:bg-[#EEEAF7]'"
                    >
                        <PackageCheck class="w-4 h-4" />
                        <span>Por recibir</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums"
                            :class="currentTab === 'por-recibir' ? 'bg-white/20 text-white' : 'bg-white text-[#1E1545]'"
                        >
                            {{ fixedPendingCount }}
                        </span>
                    </button>

                    <button
                        @click="setTab('por-llenar')"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition min-h-[42px] inline-flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por-llenar'
                            ? 'bg-[#0071BA] text-white border-[#0071BA]'
                            : 'bg-[#F4F3F8] text-[#292638] border-[#E9E7F0] hover:bg-[#E9F3F8]'"
                    >
                        <Droplets class="w-4 h-4" />
                        <span>Por llenar</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums"
                            :class="currentTab === 'por-llenar' ? 'bg-white/20 text-white' : 'bg-white text-[#0071BA]'"
                        >
                            {{ fixedToFillCount }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- PESTAÑA 1: POR RECIBIR (CON BOTÓN DIRECTO "RECIBIR Y LLENAR")             -->
            <!-- ========================================================================= -->
            <div v-if="currentTab === 'por-recibir'" class="space-y-3">
                <div
                    v-if="fixedPending.length > 0"
                    class="flex flex-wrap items-center justify-between gap-2.5 bg-white px-4 py-3 rounded-2xl border border-[#E9E7F0] shadow-2xs"
                >
                    <span class="text-xs font-semibold text-[#747181] tabular-nums">
                        {{ fixedPending.length }} {{ fixedPending.length === 1 ? 'pedido' : 'pedidos' }} · {{ totalPendingBottles }} envases por recibir
                    </span>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="handleReceiveOnlyAll"
                            :disabled="batchProcessing || eligibleForBatch.length === 0"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border border-[#E9E7F0] bg-white hover:bg-[#F4F3F8] text-[#292638] transition min-h-[40px] cursor-pointer disabled:opacity-50"
                            title="Recibir en Planta para lavar después"
                        >
                            <Check class="w-3.5 h-3.5 text-[#0071BA]" />
                            <span>Solo recibir todos</span>
                        </button>

                        <button
                            @click="handleReceiveAndFillAll"
                            :disabled="batchProcessing || eligibleForBatch.length === 0"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#1E1545] hover:bg-[#2B1F60] text-white transition min-h-[40px] cursor-pointer disabled:opacity-50"
                        >
                            <CheckCheck class="w-4 h-4" />
                            <span>Recibir y llenar todos</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden">
                    <div v-if="fixedPending.length === 0" class="p-10 text-center space-y-2">
                        <div class="w-11 h-11 rounded-2xl bg-[#E9F3F8] text-[#0071BA] mx-auto flex items-center justify-center">
                            <PackageCheck class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-bold text-[#292638]">No hay fijos pendientes de recibir</p>
                        <p class="text-xs text-[#747181]">Cuando el repartidor recoja fijos de clientes, aparecerán aquí.</p>
                    </div>

                    <div v-else class="divide-y divide-[#E9E7F0]">
                        <div
                            v-for="item in fixedPending"
                            :key="item.order_item_id"
                            class="p-4 sm:px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-[#F4F3F8]/50 transition"
                        >
                            <!-- Cliente + cantidad -->
                            <div class="flex items-center justify-between sm:justify-start gap-3 min-w-0">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-[#292638] text-sm sm:text-base truncate">
                                            {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-[#EEEAF7] text-[#1E1545] tabular-nums shrink-0">
                                            {{ item.expected_quantity }} {{ item.expected_quantity === 1 ? 'fijo' : 'fijos' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#747181] truncate mt-0.5">
                                        <span v-if="item.customer_nickname && item.customer_name">{{ item.customer_name }} · </span>
                                        <span class="font-mono">{{ item.order_folio }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Acciones directas y claras -->
                            <div class="flex items-center justify-end gap-2 shrink-0">
                                <button
                                    @click="openReceptionIncidentModal(item)"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#9A670E] bg-[#FEF5E6] hover:bg-[#FDEBC8] border border-[#F5DFB5] transition cursor-pointer"
                                    title="Reportar faltantes o envase dañado"
                                >
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">Incidencia</span>
                                </button>

                                <button
                                    @click="handleReceiveOnlySingle(item)"
                                    :disabled="Boolean(submittingItem[item.order_item_id])"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#0071BA] bg-[#E9F3F8] hover:bg-[#D5EAF5] border border-[#BDDDF0] transition cursor-pointer disabled:opacity-50"
                                    title="Recibir y dejar en 'Por llenar'"
                                >
                                    <Check class="w-3.5 h-3.5" />
                                    <span>Solo recibir</span>
                                </button>

                                <button
                                    @click="handleReceiveAndFillSingle(item)"
                                    :disabled="Boolean(submittingItem[item.order_item_id])"
                                    type="button"
                                    class="h-10 px-3.5 rounded-xl flex items-center gap-1.5 text-xs font-bold text-white bg-[#26845B] hover:bg-[#1F6D4B] transition cursor-pointer disabled:opacity-50 shadow-2xs"
                                    title="Recibir y marcar llenados en un paso"
                                >
                                    <Sparkles class="w-3.5 h-3.5" />
                                    <span>{{ submittingItem[item.order_item_id] === 'both' ? 'Guardando...' : 'Recibir y llenar' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- PESTAÑA 2: POR LLENAR (1 TOQUE COMPLETO + PARCIAL OPCIONAL)               -->
            <!-- ========================================================================= -->
            <div v-if="currentTab === 'por-llenar'" class="space-y-3">
                <div
                    v-if="fixedToFill.length > 0"
                    class="flex items-center justify-between gap-3 bg-white px-4 py-3 rounded-2xl border border-[#E9E7F0] shadow-2xs"
                >
                    <span class="text-xs font-semibold text-[#747181] tabular-nums">
                        {{ fixedToFill.length }} {{ fixedToFill.length === 1 ? 'pedido' : 'pedidos' }} · {{ totalFillBottles }} envases en planta
                    </span>

                    <button
                        @click="handleFillAllDirect"
                        :disabled="batchProcessing"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-[#0071BA] hover:bg-[#005E9B] text-white transition min-h-[40px] cursor-pointer disabled:opacity-50"
                    >
                        <CheckCheck class="w-4 h-4" />
                        <span>Llenar todos ({{ totalFillBottles }})</span>
                    </button>
                </div>

                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden">
                    <div v-if="fixedToFill.length === 0" class="p-10 text-center space-y-2">
                        <div class="w-11 h-11 rounded-2xl bg-[#E8F2EC] text-[#26845B] mx-auto flex items-center justify-center">
                            <Droplets class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-bold text-[#292638]">No hay fijos pendientes por llenar</p>
                        <p class="text-xs text-[#747181]">Todo envase marcado como lleno pasa directo a Reparto.</p>
                    </div>

                    <div v-else class="divide-y divide-[#E9E7F0]">
                        <div
                            v-for="item in fixedToFill"
                            :key="item.order_item_id"
                            class="p-4 sm:px-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-[#F4F3F8]/50 transition"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-[#292638] text-sm sm:text-base truncate">
                                        {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-[#E9F3F8] text-[#0071BA] tabular-nums shrink-0">
                                        {{ item.pending_quantity }} {{ item.pending_quantity === 1 ? 'fijo' : 'fijos' }}
                                    </span>
                                </div>
                                <p class="text-xs text-[#747181] truncate mt-0.5">
                                    <span v-if="item.customer_nickname && item.customer_name">{{ item.customer_name }} · </span>
                                    <span class="font-mono">{{ item.order_folio }}</span>
                                </p>
                            </div>

                            <div class="flex items-center justify-end gap-2 shrink-0">
                                <button
                                    @click="openFillIncidentModal(item)"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#9A670E] bg-[#FEF5E6] hover:bg-[#FDEBC8] border border-[#F5DFB5] transition cursor-pointer"
                                    title="Reportar envase dañado en lavado/llenado"
                                >
                                    <AlertTriangle class="w-3.5 h-3.5" />
                                    <span class="hidden sm:inline">Incidencia</span>
                                </button>

                                <button
                                    v-if="item.pending_quantity > 1"
                                    @click="openFillModal(item)"
                                    type="button"
                                    class="h-10 px-3 rounded-xl flex items-center gap-1.5 text-xs font-semibold text-[#292638] bg-[#F4F3F8] hover:bg-[#E9E7F0] border border-[#E9E7F0] transition cursor-pointer"
                                    title="Llenar cantidad parcial"
                                >
                                    <SlidersHorizontal class="w-3.5 h-3.5" />
                                    <span>Parcial</span>
                                </button>

                                <button
                                    @click="handleFillSingleDirect(item)"
                                    :disabled="Boolean(submittingItem[item.order_item_id])"
                                    type="button"
                                    class="h-10 px-3.5 rounded-xl flex items-center gap-1.5 text-xs font-bold text-white bg-[#26845B] hover:bg-[#1F6D4B] transition cursor-pointer disabled:opacity-50 shadow-2xs"
                                >
                                    <Check class="w-3.5 h-3.5" />
                                    <span>{{ submittingItem[item.order_item_id] === 'fill' ? 'Guardando...' : `Llenar (${item.pending_quantity})` }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: INCIDENCIA EN RECEPCIÓN -->
        <div
            v-if="showReceptionIncidentModal && itemForReceptionIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#9A670E] uppercase tracking-wider block">Incidencia en recepción</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemForReceptionIncident.customer_nickname || itemForReceptionIncident.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-[#747181]">Esperados: {{ itemForReceptionIncident.expected_quantity }} fijos</p>
                    </div>
                    <button
                        @click="closeReceptionIncidentModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-[#E8F2EC] rounded-xl border border-[#BBE4CE] space-y-1">
                            <label class="font-bold text-[#26845B] block">Aptos recibidos</label>
                            <input
                                v-model.number="receptionIncidentForm.received_quantity"
                                type="number"
                                min="0"
                                :max="itemForReceptionIncident.expected_quantity"
                                class="w-full text-base font-bold text-[#292638] tabular-nums rounded-xl border-[#BBE4CE] bg-white"
                            />
                        </div>

                        <div class="p-3 bg-[#FEF5E6] rounded-xl border border-[#F5DFB5] space-y-1">
                            <label class="font-bold text-[#9A670E] block">Dañados / rotos</label>
                            <input
                                v-model.number="receptionIncidentForm.damaged_quantity"
                                type="number"
                                min="0"
                                :max="itemForReceptionIncident.expected_quantity"
                                class="w-full text-base font-bold text-[#292638] tabular-nums rounded-xl border-[#F5DFB5] bg-white"
                            />
                        </div>
                    </div>

                    <div v-if="receptionIncidentForm.damaged_quantity > 0" class="p-3 bg-[#F4F3F8] rounded-xl border border-[#E9E7F0] space-y-2">
                        <label class="font-semibold text-[#292638] block">Tipo y descripción del daño</label>
                        <select v-model="receptionIncidentForm.damage_type" class="w-full text-xs rounded-xl border-[#E9E7F0]">
                            <option value="rotura">Rotura / Quebrado</option>
                            <option value="fisura">Fisura / Fuga</option>
                            <option value="contaminacion">Contaminación</option>
                            <option value="deformacion">Deformación</option>
                            <option value="otro">Otro</option>
                        </select>
                        <input
                            v-model="receptionIncidentForm.damage_notes"
                            type="text"
                            placeholder="Descripción del daño..."
                            class="w-full text-xs rounded-xl border-[#E9E7F0]"
                        />
                    </div>

                    <div v-if="receptionShortage > 0" class="p-3 bg-[#FCECEF] rounded-xl border border-[#F6C8D2] space-y-1">
                        <label class="font-bold text-[#B91C3C] block">Faltan {{ receptionShortage }} envase(s). Motivo:</label>
                        <input
                            v-model="receptionIncidentForm.difference_reason"
                            type="text"
                            placeholder="Ej. Chofer entregó 1 menos..."
                            class="w-full text-xs rounded-xl border-[#F6C8D2] bg-white"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeReceptionIncidentModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitReceptionIncident"
                        :disabled="receptionIncidentForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#D99A27] hover:bg-[#C0851B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ receptionIncidentForm.processing ? 'Guardando...' : 'Guardar incidencia' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: LLENADO PARCIAL (SOLO EXCEPCIÓN) -->
        <div
            v-if="showFillModal && itemToFill"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#0071BA] uppercase tracking-wider block">Llenado parcial</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemToFill.customer_nickname || itemToFill.customer_name || 'Cliente' }}
                        </h3>
                    </div>
                    <button
                        @click="closeFillModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-4 bg-[#F4F3F8] rounded-xl text-center space-y-2">
                    <span class="text-xs font-semibold text-[#747181] block">Unidades llenadas (de {{ itemToFill.pending_quantity }})</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="decrementFillQty"
                            :disabled="fillForm.quantity <= 1"
                            type="button"
                            class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#292638] font-bold disabled:opacity-40 cursor-pointer"
                        >-</button>
                        <span class="text-3xl font-black text-[#1E1545] tabular-nums w-12 text-center">{{ fillForm.quantity }}</span>
                        <button
                            @click="incrementFillQty"
                            :disabled="fillForm.quantity >= itemToFill.pending_quantity"
                            type="button"
                            class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#292638] font-bold disabled:opacity-40 cursor-pointer"
                        >+</button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeFillModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitFill"
                        :disabled="fillForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ fillForm.processing ? 'Guardando...' : `Confirmar (${fillForm.quantity})` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: INCIDENCIA EN LLENADO -->
        <div
            v-if="showFillIncidentModal && itemForFillIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#1E1545]/50 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                    <div>
                        <span class="text-[11px] font-bold text-[#9A670E] uppercase tracking-wider block">Incidencia en llenado</span>
                        <h3 class="text-base font-bold text-[#292638]">
                            {{ itemForFillIncident.customer_nickname || itemForFillIncident.customer_name || 'Cliente' }}
                        </h3>
                    </div>
                    <button
                        @click="closeFillIncidentModal"
                        type="button"
                        class="h-9 w-9 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-semibold text-[#292638] block mb-1">Cantidad dañada (máx. {{ itemForFillIncident.pending_quantity }})</label>
                        <input
                            v-model.number="fillIncidentForm.quantity"
                            type="number"
                            min="1"
                            :max="itemForFillIncident.pending_quantity"
                            class="w-28 text-center text-base font-bold tabular-nums rounded-xl border-[#E9E7F0]"
                        />
                    </div>
                    <div>
                        <label class="font-semibold text-[#292638] block mb-1">Tipo de daño</label>
                        <select v-model="fillIncidentForm.damage_type" class="w-full text-xs rounded-xl border-[#E9E7F0]">
                            <option value="rotura">Rotura</option>
                            <option value="fisura">Fisura / Fuga</option>
                            <option value="contaminacion">Contaminación</option>
                            <option value="deformacion">Deformación</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="font-semibold text-[#292638] block mb-1">Descripción (obligatorio)</label>
                        <textarea
                            v-model="fillIncidentForm.notes"
                            rows="2"
                            placeholder="Detalle del daño..."
                            class="w-full text-xs rounded-xl border-[#E9E7F0]"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                    <button
                        @click="closeFillIncidentModal"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#292638] text-xs font-semibold min-h-[40px] cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitFillIncident"
                        :disabled="fillIncidentForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl bg-[#D99A27] hover:bg-[#C0851B] text-white text-xs font-bold min-h-[40px] cursor-pointer"
                    >
                        {{ fillIncidentForm.processing ? 'Registrando...' : 'Registrar daño' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
