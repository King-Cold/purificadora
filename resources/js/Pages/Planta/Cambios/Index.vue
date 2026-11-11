<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    balances: {
        type: Object,
        default: () => ({
            exchange_empty_available: 0,
            exchange_washing_filling: 0,
            exchange_full_ready: 0,
            exchange_full_reserved: 0,
            exchange_unlocated: 0,
            exchange_damaged_total: 0,
        }),
    },
    supervisionSummary: {
        type: Object,
        default: () => null,
    },
    activeBatches: {
        type: Array,
        default: () => [],
    },
    initialCountPending: {
        type: Boolean,
        default: false,
    },
    initialCountInfo: {
        type: Object,
        default: null,
    },
    pendingExchangeReceptions: {
        type: Array,
        default: () => [],
    },
    pendingExchangeProposals: {
        type: Array,
        default: () => [],
    },
    recentExchangeMovements: {
        type: Array,
        default: () => [],
    },
    drivers: {
        type: Array,
        default: () => [],
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
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// =========================================================================
// ACCIÓN 1: RECIBIR VACÍOS (Descarga de vacíos del chofer a Planta)
// =========================================================================
const emptyReceptionStates = ref({});

const initEmptyReceptionState = (movementId, declaredQty) => {
    if (!emptyReceptionStates.value[movementId]) {
        emptyReceptionStates.value[movementId] = {
            received_quantity: declaredQty,
            damaged_quantity: 0,
            damage_type: 'rotura',
            damage_notes: 'Envase dañado en recepción de camión',
            unlocated_quantity: 0,
            difference_reason: '',
        };
    }
    return emptyReceptionStates.value[movementId];
};

const submittingReception = ref({});

const submitConfirmExchange = (item) => {
    const st = initEmptyReceptionState(item.movement_id, item.quantity_declared);
    const received = Number(st.received_quantity) || 0;
    const damaged = Number(st.damaged_quantity) || 0;
    const unlocated = Number(st.unlocated_quantity) || 0;
    const declared = item.quantity_declared;

    if ((received + damaged) !== declared && (!st.difference_reason || st.difference_reason.trim().length < 5)) {
        alert('Indica el motivo de la diferencia (mínimo 5 caracteres) cuando lo recibido apto difiere de lo declarado.');
        return;
    }

    submittingReception.value[item.movement_id] = true;

    router.post(route('planta.reception.confirm-exchange'), {
        movement_id: item.movement_id,
        received_quantity: received,
        damaged_quantity: damaged,
        damage_type: damaged > 0 ? st.damage_type : null,
        damage_notes: damaged > 0 ? (st.damage_notes || 'Dañado al recibir en Planta') : null,
        unlocated_quantity: unlocated,
        difference_reason: (received + damaged) !== declared ? st.difference_reason : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete emptyReceptionStates.value[item.movement_id];
            submittingReception.value[item.movement_id] = false;
        },
        onError: () => {
            submittingReception.value[item.movement_id] = false;
        },
        onFinish: () => {
            submittingReception.value[item.movement_id] = false;
        },
    });
};

// =========================================================================
// ACCIÓN 2: PREPARAR CAMBIOS (Lavado y Llenado interno + atajo Preparar carga)
// =========================================================================
const showProductionPanel = ref(true);
const washForm = useForm({
    quantity: Math.max(1, Math.min(20, props.balances.exchange_empty_available || 10)),
});
const finishForm = useForm({
    quantity: Math.max(1, Math.min(20, props.balances.exchange_washing_filling || 10)),
});
const lastFinishedQty = ref(0);

const submitStartExchangeWash = () => {
    if (washForm.processing) return;
    washForm.post(route('planta.inventory.production.start'), {
        preserveScroll: true,
        onSuccess: () => {
            finishForm.quantity = washForm.quantity;
        },
    });
};

const submitFinishExchangeWash = () => {
    if (finishForm.processing) return;
    const finishedNow = Number(finishForm.quantity) || 0;
    finishForm.post(route('planta.inventory.production.finish'), {
        preserveScroll: true,
        onSuccess: () => {
            lastFinishedQty.value = finishedNow;
            sendForm.quantity = Math.max(1, finishedNow);
        },
    });
};

const sendBlockRef = ref(null);
const focusPrepareLoad = (qty) => {
    if (qty > 0) {
        sendForm.quantity = Math.min(qty, props.balances.exchange_full_ready || qty);
    }
    if (sendBlockRef.value) {
        sendBlockRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

// =========================================================================
// ACCIÓN 3: ENVIAR LLENOS (Propuesta de carga de cambios al camión)
// =========================================================================
const sendForm = useForm({
    bottle_type: 'cambio',
    quantity: Math.max(1, Math.min(20, props.balances.exchange_full_ready || 5)),
    notes: '',
});

const sendIncrement = (delta = 1) => {
    const current = Number(sendForm.quantity) || 1;
    const max = props.balances.exchange_full_ready || 999;
    sendForm.quantity = Math.max(1, Math.min(max, current + delta));
};

const sendDecrement = (delta = 1) => {
    const current = Number(sendForm.quantity) || 1;
    sendForm.quantity = Math.max(1, current - delta);
};

const submitSendFull = () => {
    if (sendForm.processing) return;
    if (sendForm.quantity <= 0) {
        alert('Indica una cantidad válida de botellones llenos a enviar.');
        return;
    }
    if (sendForm.quantity > props.balances.exchange_full_ready) {
        alert(`No hay suficientes botellones llenos libres en Planta (${props.balances.exchange_full_ready} disponibles).`);
        return;
    }

    sendForm.post(route('planta.loads.store'), {
        preserveScroll: true,
        onSuccess: () => {
            lastFinishedQty.value = 0;
            sendForm.reset('notes');
            sendForm.quantity = Math.max(1, Math.min(10, props.balances.exchange_full_ready));
        },
    });
};

const withdrawProposal = (proposalId) => {
    if (!confirm('¿Deseas retirar esta propuesta de carga de cambios?')) return;
    router.post(route('planta.loads.withdraw', proposalId), {}, {
        preserveScroll: true,
    });
};

// =========================================================================
// BOTÓN ÁMBAR: REPORTAR INCONVENIENTE
// =========================================================================
const showInconvenienceModal = ref(false);
const inconvenienceForm = useForm({
    quantity: 1,
    origin_stage: 'plant_empty',
    reason: 'rotura',
    location_status: 'separado',
    notes: '',
});

const openInconvenienceModal = (defaultStage = 'plant_empty') => {
    inconvenienceForm.quantity = 1;
    inconvenienceForm.origin_stage = defaultStage;
    inconvenienceForm.reason = 'rotura';
    inconvenienceForm.location_status = 'separado';
    inconvenienceForm.notes = '';
    inconvenienceForm.clearErrors();
    showInconvenienceModal.value = true;
};

const submitInconvenience = () => {
    if (inconvenienceForm.processing) return;
    inconvenienceForm.post(route('planta.cambios.report-inconvenience'), {
        preserveScroll: true,
        onSuccess: () => {
            showInconvenienceModal.value = false;
        },
    });
};

// =========================================================================
// CONTEO INICIAL SI ESTÁ PENDIENTE
// =========================================================================
const showInitialCountModal = ref(false);
const initialCountForm = useForm({
    empty_count: 0,
    full_count: 0,
    notes: 'Conteo físico inicial de arranque de operaciones en Planta.',
});

const submitInitialCount = () => {
    if (initialCountForm.processing) return;
    initialCountForm.post(route('planta.inventory.initial-count'), {
        preserveScroll: true,
        onSuccess: () => {
            showInitialCountModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Planta · Cambios" />

    <AuthenticatedLayout wide>
        <div class="py-5 max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 space-y-5">

            <!-- Mensajes Flash -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between gap-3 shadow-2xs"
                role="alert"
            >
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                    <p class="text-sm font-bold">{{ flashSuccess }}</p>
                </div>

                <!-- Atajo directo "Preparar carga" al terminar un lote de cambios -->
                <button
                    v-if="lastFinishedQty > 0 && balances.exchange_full_ready > 0"
                    type="button"
                    @click="focusPrepareLoad(lastFinishedQty)"
                    class="min-h-[44px] px-4 py-2 rounded-xl bg-[#0E1E42] hover:bg-blue-900 text-white text-xs font-black flex items-center gap-2 shrink-0 cursor-pointer shadow-2xs"
                >
                    <i class="fa-solid fa-truck-arrow-right text-sky-400"></i>
                    <span>Preparar carga ({{ lastFinishedQty }})</span>
                </button>
            </div>

            <div
                v-if="flashError || Object.keys(page.props.errors || {}).length > 0"
                class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                </div>
                <div class="text-sm font-bold">
                    <p v-if="flashError">{{ flashError }}</p>
                    <p v-for="(err, key) in page.props.errors" :key="key">{{ err }}</p>
                </div>
            </div>

            <!-- AVISO DE CONTEO INICIAL PENDIENTE -->
            <div
                v-if="initialCountPending"
                class="p-4 sm:p-5 rounded-3xl bg-amber-50 border border-amber-200 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-2xs"
            >
                <div class="flex items-center gap-3.5">
                    <div class="h-10 w-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-clipboard-check text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-amber-900">Conteo físico inicial pendiente</h2>
                        <p class="text-xs text-amber-800">
                            Registra el conteo inicial de vacíos y llenos de 20 L en Planta para establecer la línea base.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="showInitialCountModal = true"
                    class="min-h-[44px] px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs flex items-center justify-center gap-2 transition cursor-pointer shrink-0"
                >
                    <i class="fa-solid fa-pencil"></i>
                    <span>Registrar conteo inicial</span>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- ENCABEZADO Y CANTIDADES LISTAS PARA USAR EN PRIMER LUGAR                  -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-[#0E1E42] text-white flex items-center justify-center shadow-2xs shrink-0">
                            <i class="fa-solid fa-arrows-rotate text-xl text-sky-400"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                                    Cambios en Planta
                                </h1>
                                <span
                                    v-if="supervisionSummary"
                                    class="px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold"
                                >
                                    {{ supervisionSummary.active_total }} activos · Meta {{ supervisionSummary.target_quantity }}
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm font-medium text-slate-500">
                                Recibe vacíos del camión, prepara lotes de 20 L y envía llenos al Repartidor
                            </p>
                        </div>
                    </div>

                    <!-- Botones de acción superior: Preparar cambios + Reportar inconveniente (ámbar) -->
                    <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
                        <button
                            type="button"
                            @click="openInconvenienceModal('plant_empty')"
                            class="min-h-[44px] px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs sm:text-sm flex items-center gap-2 transition cursor-pointer shadow-2xs"
                        >
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Reportar inconveniente</span>
                        </button>
                    </div>
                </div>

                <!-- CANTIDADES LISTAS PARA USAR (PRIMERO LLENOS LIBRES Y VACÍOS APTOS) -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
                    <!-- 1. Llenos libres en Planta (Listos para enviar) -->
                    <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200/80">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-900 block">
                            Llenos libres en Planta
                        </span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-black text-emerald-700">{{ balances.exchange_full_ready }}</span>
                            <span class="text-xs text-emerald-700 font-semibold">listos para enviar</span>
                        </div>
                    </div>

                    <!-- 2. Vacíos aptos en Planta (Listos para preparar) -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600 block">
                            Vacíos aptos en Planta
                        </span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-black text-slate-900">{{ balances.exchange_empty_available }}</span>
                            <span class="text-xs text-slate-500 font-semibold">para preparar</span>
                        </div>
                    </div>

                    <!-- 3. En preparación (Lavado y llenado) -->
                    <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-200/80">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-950 block">
                            En preparación
                        </span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-black text-[#0E1E42]">{{ balances.exchange_washing_filling }}</span>
                            <span class="text-xs text-blue-900/70 font-semibold">en lavado y llenado</span>
                        </div>
                    </div>

                    <!-- 4. Llenos reservados / En revisión -->
                    <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/80">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-900 block">
                            Reservados para camión
                        </span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-3xl font-black text-amber-800">{{ balances.exchange_full_reserved }}</span>
                            <span class="text-xs text-amber-800 font-semibold">pendientes de confirmar</span>
                        </div>
                        <p v-if="(balances.exchange_damaged_total + balances.exchange_unlocated) > 0" class="text-[11px] font-bold text-amber-900 mt-1">
                            {{ balances.exchange_damaged_total }} dañado(s) en revisión · {{ balances.exchange_unlocated }} sin localizar
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- ACCIÓN 1 Y ACCIÓN 3 LADO A LADO EN TABLET HORIZONTAL                      -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                <!-- ========================================================= -->
                <!-- 1. RECIBIR VACÍOS DEL CAMIÓN                              -->
                <!-- ========================================================= -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-blue-50 text-[#0E1E42] flex items-center justify-center font-black text-sm shrink-0">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                            <div>
                                <h2 class="text-base sm:text-lg font-black text-slate-900">
                                    Recibir vacíos
                                </h2>
                                <p class="text-xs text-slate-500">
                                    Confirma cuántos vacíos llegaron físicamente del camión y cuántos están aptos
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="pendingExchangeReceptions.length > 0"
                            class="px-2.5 py-1 rounded-full bg-amber-500 text-white text-xs font-black"
                        >
                            {{ pendingExchangeReceptions.length }}
                        </span>
                    </div>

                    <div v-if="pendingExchangeReceptions.length === 0" class="py-8 text-center text-slate-400">
                        <i class="fa-solid fa-circle-check text-2xl mb-2 text-emerald-500/70"></i>
                        <p class="text-sm font-bold text-slate-600">No hay entregas de vacíos pendientes de recibir.</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Cuando el Repartidor declare una descarga de vacíos en «Mi carga», aparecerá aquí.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="item in pendingExchangeReceptions"
                            :key="item.movement_id"
                            class="bg-slate-50 rounded-2xl p-4 border border-slate-200 space-y-3.5"
                        >
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <span class="text-xs font-black text-[#0E1E42] bg-white px-2.5 py-1 rounded-lg border border-slate-200">
                                        {{ item.quantity_declared }} vacíos declarados
                                    </span>
                                    <span class="text-xs font-bold text-slate-700 ml-2">
                                        por {{ item.driver_name }}
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">{{ item.created_at }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">
                                        Vacíos aptos recibidos *
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        :max="item.quantity_declared"
                                        v-model.number="initEmptyReceptionState(item.movement_id, item.quantity_declared).received_quantity"
                                        class="w-full h-11 text-center font-black text-slate-900 bg-white border border-slate-300 rounded-xl text-lg"
                                    />
                                </div>

                                <div class="bg-white p-3 rounded-xl border border-slate-200">
                                    <label class="block text-xs font-bold text-amber-800 mb-1">
                                        Dañados al recibir (En revisión)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        :max="item.quantity_declared"
                                        v-model.number="initEmptyReceptionState(item.movement_id, item.quantity_declared).damaged_quantity"
                                        class="w-full h-11 text-center font-black text-amber-900 bg-white border border-slate-300 rounded-xl text-lg"
                                    />
                                </div>
                            </div>

                            <!-- Motivo de daño si hay dañados -->
                            <div
                                v-if="Number(initEmptyReceptionState(item.movement_id, item.quantity_declared).damaged_quantity) > 0"
                                class="p-3 bg-amber-50 rounded-xl border border-amber-200 grid grid-cols-1 sm:grid-cols-2 gap-2.5"
                            >
                                <div>
                                    <label class="text-[11px] font-bold text-amber-900 block mb-1">Motivo del daño</label>
                                    <select
                                        v-model="initEmptyReceptionState(item.movement_id, item.quantity_declared).damage_type"
                                        class="w-full h-10 text-xs font-semibold rounded-lg border-amber-300 bg-white"
                                    >
                                        <option value="rotura">Roto</option>
                                        <option value="fisura">Fisurado</option>
                                        <option value="contaminacion">Contaminado</option>
                                        <option value="deformacion">Deformado</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[11px] font-bold text-amber-900 block mb-1">Detalle del daño</label>
                                    <input
                                        type="text"
                                        v-model="initEmptyReceptionState(item.movement_id, item.quantity_declared).damage_notes"
                                        placeholder="Ej: Cuello fisurado al descargar"
                                        class="w-full h-10 text-xs rounded-lg border-amber-300 bg-white"
                                    />
                                </div>
                            </div>

                            <!-- Motivo si hay diferencia frente a lo declarado -->
                            <div
                                v-if="(Number(initEmptyReceptionState(item.movement_id, item.quantity_declared).received_quantity) + Number(initEmptyReceptionState(item.movement_id, item.quantity_declared).damaged_quantity)) !== item.quantity_declared"
                                class="p-3 bg-amber-50 rounded-xl border border-amber-200 space-y-2"
                            >
                                <span class="text-xs font-bold text-amber-900 block">
                                    Diferencia frente a los {{ item.quantity_declared }} declarados. Indica el motivo *:
                                </span>
                                <input
                                    type="text"
                                    v-model="initEmptyReceptionState(item.movement_id, item.quantity_declared).difference_reason"
                                    placeholder="Ej: 1 vacío quedó en el camión para siguiente vuelta"
                                    required
                                    class="w-full h-10 text-xs rounded-lg border-amber-300 bg-white text-slate-800"
                                />
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button
                                    type="button"
                                    @click="submitConfirmExchange(item)"
                                    :disabled="submittingReception[item.movement_id]"
                                    class="min-h-[44px] px-5 py-2.5 rounded-xl bg-[#0E1E42] text-white font-black text-xs sm:text-sm hover:bg-blue-900 transition shadow-2xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
                                >
                                    <i v-if="submittingReception[item.movement_id]" class="fa-solid fa-circle-notch fa-spin"></i>
                                    <i v-else class="fa-solid fa-circle-check text-sky-400"></i>
                                    <span>Confirmar recepción de vacíos</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- 3. ENVIAR LLENOS AL CAMIÓN                                -->
                <!-- ========================================================= -->
                <div ref="sendBlockRef" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center font-black text-sm shrink-0">
                            <i class="fa-solid fa-truck-arrow-right"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-black text-slate-900">
                                Enviar llenos
                            </h2>
                            <p class="text-xs text-slate-500">
                                Envía cambios llenos desde Planta · Quedan reservados hasta que el Repartidor confirme en «Mi carga»
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSendFull" class="space-y-4">
                        <div class="flex flex-col items-center justify-center bg-slate-50 rounded-2xl border border-slate-200/80 p-4 space-y-3.5">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Cantidad de cambios llenos a enviar (20 L)
                            </label>

                            <div class="flex items-center justify-center gap-4 w-full max-w-xs">
                                <button
                                    type="button"
                                    @click="sendDecrement(1)"
                                    :disabled="sendForm.quantity <= 1 || sendForm.processing"
                                    class="h-12 w-12 min-h-[44px] min-w-[44px] rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-black text-xl flex items-center justify-center cursor-pointer shadow-2xs disabled:opacity-30"
                                >-</button>

                                <input
                                    type="number"
                                    min="1"
                                    :max="balances.exchange_full_ready"
                                    v-model.number="sendForm.quantity"
                                    class="w-24 h-13 text-center text-2xl font-black text-slate-900 bg-white border-2 border-[#0E1E42]/30 rounded-xl"
                                />

                                <button
                                    type="button"
                                    @click="sendIncrement(1)"
                                    :disabled="sendForm.quantity >= balances.exchange_full_ready || sendForm.processing"
                                    class="h-12 w-12 min-h-[44px] min-w-[44px] rounded-xl bg-[#0E1E42] text-white hover:bg-blue-900 font-black text-xl flex items-center justify-center cursor-pointer shadow-2xs disabled:opacity-30"
                                >+</button>
                            </div>

                            <!-- Botones rápidos táctiles -->
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <button
                                    v-for="qty in [5, 10, 14, 15, 20]"
                                    :key="qty"
                                    type="button"
                                    @click="sendForm.quantity = Math.min(qty, balances.exchange_full_ready || qty)"
                                    class="min-h-[44px] min-w-[44px] px-3.5 py-2 rounded-xl text-xs font-bold bg-white border border-slate-200 hover:bg-slate-100 cursor-pointer shadow-2xs"
                                >
                                    {{ qty }}
                                </button>
                            </div>

                            <p class="text-xs text-slate-600 text-center">
                                Llenos libres en Planta: <strong class="text-emerald-700">{{ balances.exchange_full_ready }} botellones</strong>
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="sendForm.processing || balances.exchange_full_ready <= 0 || sendForm.quantity <= 0"
                            class="w-full min-h-[48px] rounded-2xl bg-[#0E1E42] hover:bg-blue-900 text-white font-black text-sm sm:text-base flex items-center justify-center gap-2 shadow-sm transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <i v-if="sendForm.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                            <i v-else class="fa-solid fa-paper-plane text-sky-400"></i>
                            <span>Enviar {{ sendForm.quantity }} lleno{{ sendForm.quantity > 1 ? 's' : '' }} al camión</span>
                        </button>
                    </form>

                    <!-- Propuestas enviadas pendientes de confirmar -->
                    <div v-if="pendingExchangeProposals.length > 0" class="pt-3 border-t border-slate-100 space-y-2">
                        <span class="text-xs font-bold text-amber-900 block">
                            Reservados en espera de confirmación del Repartidor:
                        </span>

                        <div
                            v-for="prop in pendingExchangeProposals"
                            :key="prop.id"
                            class="p-3 bg-amber-50 rounded-xl border border-amber-200 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="h-8 w-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black">
                                    {{ prop.quantity }}
                                </span>
                                <div>
                                    <p class="font-bold text-slate-900">{{ prop.quantity }} llenos pendientes de confirmar</p>
                                    <p class="text-[11px] text-slate-500">En «Mi carga → Por cargar» · {{ prop.created_at }}</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="withdrawProposal(prop.id)"
                                class="min-h-[40px] px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 rounded-lg cursor-pointer"
                            >
                                Retirar
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- 2. PREPARAR CAMBIOS (INICIAR LOTE CON VACÍOS APTOS Y CONFIRMAR LLENOS)    -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-sky-50 text-[#0E1E42] flex items-center justify-center font-black text-sm shrink-0">
                            <i class="fa-solid fa-soap"></i>
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-black text-slate-900">
                                Preparar cambios (Lavado y llenado de 20 L)
                            </h2>
                            <p class="text-xs text-slate-500">
                                Inicia un lote con vacíos aptos disponibles y confirma cuántos terminaron llenos
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="balances.exchange_full_ready > 0"
                        type="button"
                        @click="focusPrepareLoad(balances.exchange_full_ready)"
                        class="min-h-[44px] px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-200 font-bold text-xs flex items-center gap-2 cursor-pointer self-start sm:self-auto"
                    >
                        <i class="fa-solid fa-truck-arrow-right text-emerald-700"></i>
                        <span>Preparar carga ({{ balances.exchange_full_ready }} listos)</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Paso 1: Iniciar preparación -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase text-slate-700">1. Iniciar lote de preparación</h3>
                            <span class="text-xs font-bold text-slate-500">Disponibles: {{ balances.exchange_empty_available }} vacíos</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Pasa vacíos aptos a la línea de lavado y purificación.
                        </p>
                        <form @submit.prevent="submitStartExchangeWash" class="flex items-center gap-2.5">
                            <input
                                type="number"
                                min="1"
                                :max="balances.exchange_empty_available"
                                v-model.number="washForm.quantity"
                                class="w-24 h-11 text-center font-black text-slate-900 bg-white border border-slate-300 rounded-xl text-base"
                                required
                            />
                            <button
                                type="submit"
                                :disabled="washForm.processing || balances.exchange_empty_available <= 0"
                                class="flex-1 min-h-[44px] px-4 py-2.5 rounded-xl bg-[#0E1E42] text-white font-black text-xs sm:text-sm hover:bg-blue-900 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40"
                            >
                                <i class="fa-solid fa-play text-sky-400"></i>
                                <span>Iniciar preparación</span>
                            </button>
                        </form>
                    </div>

                    <!-- Paso 2: Confirmar cuántos terminaron llenos -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase text-slate-700">2. Confirmar llenos terminados</h3>
                            <span class="text-xs font-bold text-blue-900">En preparación: {{ balances.exchange_washing_filling }} u.</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Los que aún no terminen permanecen en preparación hasta finalizar o reportar inconveniente.
                        </p>
                        <form @submit.prevent="submitFinishExchangeWash" class="flex items-center gap-2.5">
                            <input
                                type="number"
                                min="1"
                                :max="balances.exchange_washing_filling"
                                v-model.number="finishForm.quantity"
                                class="w-24 h-11 text-center font-black text-slate-900 bg-white border border-slate-300 rounded-xl text-base"
                                required
                            />
                            <button
                                type="submit"
                                :disabled="finishForm.processing || balances.exchange_washing_filling <= 0"
                                class="flex-1 min-h-[44px] px-4 py-2.5 rounded-xl bg-emerald-700 text-white font-black text-xs sm:text-sm hover:bg-emerald-800 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40"
                            >
                                <i class="fa-solid fa-check text-emerald-300"></i>
                                <span>Terminar llenos</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- MOVIMIENTOS RECIENTES DE CAMBIOS EN PLANTA                                -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-500 text-sm"></i>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                            Movimientos recientes de cambios
                        </h2>
                    </div>
                    <span class="text-xs text-slate-500 font-semibold">20 L · Independientes por operación</span>
                </div>

                <div v-if="recentExchangeMovements.length === 0" class="py-6 text-center text-xs text-slate-400">
                    No se registran movimientos recientes de cambios.
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="mov in recentExchangeMovements"
                        :key="mov.id"
                        class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'inline-flex items-center justify-center h-9 w-9 rounded-xl font-black shrink-0 text-sm',
                                    mov.type === 'truck_unload_exchange'
                                        ? 'bg-blue-50 text-[#0E1E42]'
                                        : (mov.type === 'truck_load_exchange' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700')
                                ]"
                            >
                                {{ mov.quantity }}
                            </span>
                            <div>
                                <span class="font-bold text-slate-900 block">
                                    {{ mov.type === 'truck_unload_exchange' ? 'Vacíos recibidos del camión' : (mov.type === 'truck_load_exchange' ? 'Llenos enviados al camión' : 'Preparación en Planta') }}
                                </span>
                                <span class="text-slate-500 text-[11px]">
                                    {{ mov.date }} · Operador: {{ mov.operator_name }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right self-start sm:self-auto">
                            <span
                                :class="[
                                    'inline-block px-2.5 py-1 rounded-full text-[11px] font-bold',
                                    mov.difference_reason ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700'
                                ]"
                            >
                                {{ mov.difference_reason ? 'Con diferencia aclarada' : 'Confirmado' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: REPORTAR INCONVENIENTE (BOTÓN ÁMBAR) -->
            <div
                v-if="showInconvenienceModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            >
                <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 max-w-md w-full space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-black text-slate-900">Reportar inconveniente</h2>
                                <p class="text-xs text-slate-500">Envase roto, dañado o sin localizar en Planta</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showInconvenienceModal = false"
                            class="h-9 w-9 rounded-lg text-slate-400 hover:text-slate-700 flex items-center justify-center cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form @submit.prevent="submitInconvenience" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Cantidad *</label>
                                <input
                                    type="number"
                                    min="1"
                                    v-model.number="inconvenienceForm.quantity"
                                    required
                                    class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Etapa en Planta *</label>
                                <select
                                    v-model="inconvenienceForm.origin_stage"
                                    class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                                >
                                    <option value="plant_empty">Vacíos aptos ({{ balances.exchange_empty_available }})</option>
                                    <option value="plant_preparation">En preparación ({{ balances.exchange_washing_filling }})</option>
                                    <option value="plant_full">Llenos libres ({{ balances.exchange_full_ready }})</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Motivo *</label>
                            <select
                                v-model="inconvenienceForm.reason"
                                class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                            >
                                <option value="rotura">Roto</option>
                                <option value="fisura">Fisurado</option>
                                <option value="tapa_cuello">Tapa o cuello dañado</option>
                                <option value="contaminacion">Contaminado</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Ubicación física *</label>
                            <select
                                v-model="inconvenienceForm.location_status"
                                class="w-full h-11 text-xs font-semibold rounded-xl border-slate-300 text-slate-800"
                            >
                                <option value="separado">Separado físicamente en Planta</option>
                                <option value="sin_localizar">Pendiente de aclarar / Sin localizar</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Observaciones *</label>
                            <input
                                type="text"
                                v-model="inconvenienceForm.notes"
                                required
                                placeholder="Ej: Botellón roto durante lavado"
                                class="w-full h-11 text-xs rounded-xl border-slate-300 text-slate-800"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                @click="showInconvenienceModal = false"
                                class="min-h-[44px] px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="inconvenienceForm.processing"
                                class="min-h-[44px] px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs cursor-pointer shadow-2xs"
                            >
                                Guardar reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: CONTEO INICIAL -->
            <div
                v-if="showInitialCountModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            >
                <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 max-w-md w-full space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h2 class="text-base font-black text-slate-900">Conteo físico inicial</h2>
                        </div>
                        <button
                            type="button"
                            @click="showInitialCountModal = false"
                            class="h-8 w-8 rounded-lg text-slate-400 hover:text-slate-700 flex items-center justify-center cursor-pointer"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <form @submit.prevent="submitInitialCount" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                                Botellones vacíos disponibles (20 L):
                            </label>
                            <input
                                type="number"
                                min="0"
                                v-model.number="initialCountForm.empty_count"
                                required
                                class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                                Botellones llenos disponibles (20 L):
                            </label>
                            <input
                                type="number"
                                min="0"
                                v-model.number="initialCountForm.full_count"
                                required
                                class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                                Observaciones:
                            </label>
                            <input
                                type="text"
                                v-model="initialCountForm.notes"
                                class="w-full text-xs rounded-xl border-slate-300 text-slate-800"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3">
                            <button
                                type="button"
                                @click="showInitialCountModal = false"
                                class="min-h-[44px] px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="initialCountForm.processing"
                                class="min-h-[44px] px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs transition cursor-pointer shadow-2xs"
                            >
                                Guardar conteo inicial
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
