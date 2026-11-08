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
        }),
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
            damage_notes: '',
            show_damaged: false,
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
    const declared = item.quantity_declared;

    if ((received + damaged) !== declared && !st.difference_reason?.trim()) {
        alert('Debe especificar el motivo de la diferencia entre lo declarado y lo recibido.');
        return;
    }

    submittingReception.value[item.movement_id] = true;

    router.post(route('planta.reception.confirm-exchange'), {
        movement_id: item.movement_id,
        received_quantity: received,
        damaged_quantity: damaged,
        damage_type: damaged > 0 ? st.damage_type : null,
        damage_notes: damaged > 0 ? st.damage_notes : null,
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
// ACCIÓN 2: ENVIAR LLENOS (Propuesta de carga de cambios al camión)
// =========================================================================
const sendForm = useForm({
    bottle_type: 'cambio',
    quantity: 5,
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
        alert('Indique una cantidad válida de botellones llenos a entregar.');
        return;
    }
    if (sendForm.quantity > props.balances.exchange_full_ready) {
        alert(`No hay suficientes botellones llenos disponibles en Planta (${props.balances.exchange_full_ready} disponibles).`);
        return;
    }

    sendForm.post(route('planta.loads.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset('notes');
            sendForm.quantity = Math.min(5, props.balances.exchange_full_ready);
        },
    });
};

// Retirar propuesta no confirmada
const withdrawProposal = (proposalId) => {
    if (!confirm('¿Desea retirar esta propuesta de carga de cambios?')) return;
    router.post(route('planta.loads.withdraw', proposalId), {}, {
        preserveScroll: true,
    });
};

// =========================================================================
// ACCIÓN SECUNDARIA: PREPARAR CAMBIOS (Lavado y Llenado interno para stock)
// =========================================================================
const showProductionModal = ref(false);
const washForm = useForm({ quantity: 10 });
const finishForm = useForm({ quantity: 10 });

const submitStartExchangeWash = () => {
    if (washForm.processing) return;
    washForm.post(route('planta.inventory.production.start'), {
        preserveScroll: true,
        onSuccess: () => {
            washForm.reset();
        },
    });
};

const submitFinishExchangeWash = () => {
    if (finishForm.processing) return;
    finishForm.post(route('planta.inventory.production.finish'), {
        preserveScroll: true,
        onSuccess: () => {
            finishForm.reset();
        },
    });
};

// Formulario de Conteo Inicial rápido si está pendiente
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
    <Head title="Planta · Intercambio de Cambios" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Mensajes Flash -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold">{{ flashSuccess }}</p>
                </div>
            </div>

            <div
                v-if="flashError"
                class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold">{{ flashError }}</p>
                </div>
            </div>

            <!-- AVISO DE CONTEO INICIAL PENDIENTE -->
            <div
                v-if="initialCountPending"
                class="p-4 sm:p-5 rounded-3xl bg-amber-50 border border-amber-200 text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm"
            >
                <div class="flex items-center gap-3.5">
                    <div class="h-10 w-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-2xs">
                        <i class="fa-solid fa-clipboard-check text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-amber-900">Conteo físico inicial pendiente</h2>
                        <p class="text-xs text-amber-800">
                            Registra el balance de arranque para habilitar el control físico formal de existencias.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="showInitialCountModal = true"
                    class="min-h-[44px] px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-black text-xs flex items-center justify-center gap-2 transition cursor-pointer shadow-2xs self-start sm:self-auto shrink-0"
                >
                    <i class="fa-solid fa-pencil"></i>
                    <span>Registrar conteo inicial</span>
                </button>
            </div>

            <!-- ENCABEZADO Y SALDOS ÚTILES DE PLANTA ARRIBA -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-blue-900 text-white flex items-center justify-center shadow-2xs shrink-0">
                            <i class="fa-solid fa-arrows-rotate text-xl text-sky-400"></i>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Cambios con el Camión
                            </h1>
                            <p class="text-xs sm:text-sm font-medium text-slate-500">
                                Intercambio físico genérico · 20L · Planta ↔ Repartidor
                            </p>
                        </div>
                    </div>

                    <!-- Acción secundaria: Preparar cambios -->
                    <button
                        type="button"
                        @click="showProductionModal = !showProductionModal"
                        class="min-h-[44px] px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center gap-2 transition cursor-pointer border border-slate-200/80 self-start sm:self-auto"
                    >
                        <i class="fa-solid fa-soap text-blue-900"></i>
                        <span>{{ showProductionModal ? 'Cerrar preparación' : 'Preparar cambios (Stock)' }}</span>
                    </button>
                </div>

                <!-- SALDOS ÚTILES DE PLANTA (TARJETAS COMPACTAS) -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                    <!-- 1. Vacíos disponibles para lavar -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Vacíos para lavar</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-2xl font-black text-slate-900">{{ balances.exchange_empty_available }}</span>
                            <span class="text-xs text-slate-400 font-semibold">20L</span>
                        </div>
                    </div>

                    <!-- 2. En lavado y llenado -->
                    <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-blue-900/70 block">En lavado y llenado</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-2xl font-black text-blue-900">{{ balances.exchange_washing_filling }}</span>
                            <span class="text-xs text-blue-900/60 font-semibold">20L</span>
                        </div>
                    </div>

                    <!-- 3. Llenos disponibles para camión -->
                    <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 block">Llenos listos para enviar</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-2xl font-black text-emerald-700">{{ balances.exchange_full_ready }}</span>
                            <span class="text-xs text-emerald-600 font-semibold">20L</span>
                        </div>
                    </div>

                    <!-- 4. Llenos reservados en propuestas -->
                    <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-100">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 block">Reservados para camión</span>
                        <div class="flex items-baseline gap-2 mt-1">
                            <span class="text-2xl font-black text-amber-700">{{ balances.exchange_full_reserved }}</span>
                            <span class="text-xs text-amber-600 font-semibold">propuestos</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACCIÓN SECUNDARIA DESPLEGABLE: PREPARAR CAMBIOS (LAVADO / LLENADO INTERNO) -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showProductionModal"
                    class="bg-white rounded-3xl border border-blue-200 shadow-sm p-6 space-y-4"
                >
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-soap text-blue-900"></i>
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                                Mesa de preparación de cambios (Reposición de existencias)
                            </h2>
                        </div>
                        <span class="text-xs font-bold text-slate-500">20 Litros</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Iniciar Lavado: Vacíos -> En lavado -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <h3 class="text-xs font-bold uppercase text-slate-600">Paso 1: Iniciar túnel de lavado</h3>
                            <p class="text-xs text-slate-500">
                                Pasa botellones vacíos de almacén a la línea de lavado y purificación.
                            </p>
                            <form @submit.prevent="submitStartExchangeWash" class="flex items-center gap-2">
                                <input
                                    type="number"
                                    min="1"
                                    :max="balances.exchange_empty_available"
                                    v-model.number="washForm.quantity"
                                    class="w-20 h-11 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-sm"
                                    required
                                />
                                <button
                                    type="submit"
                                    :disabled="washForm.processing || balances.exchange_empty_available <= 0"
                                    class="flex-1 min-h-[44px] px-4 py-2.5 rounded-xl bg-blue-900 text-white font-black text-xs hover:bg-blue-800 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40"
                                >
                                    <i v-if="washForm.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                                    <i v-else class="fa-solid fa-play text-sky-400"></i>
                                    <span>Ingresar a lavado</span>
                                </button>
                            </form>
                        </div>

                        <!-- Finalizar Llenado: En lavado -> Llenos listos -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                            <h3 class="text-xs font-bold uppercase text-slate-600">Paso 2: Finalizar purificación y llenado</h3>
                            <p class="text-xs text-slate-500">
                                Sella botellones llenos y colócalos en el almacén listos para entregar al camión.
                            </p>
                            <form @submit.prevent="submitFinishExchangeWash" class="flex items-center gap-2">
                                <input
                                    type="number"
                                    min="1"
                                    :max="balances.exchange_washing_filling"
                                    v-model.number="finishForm.quantity"
                                    class="w-20 h-11 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-sm"
                                    required
                                />
                                <button
                                    type="submit"
                                    :disabled="finishForm.processing || balances.exchange_washing_filling <= 0"
                                    class="flex-1 min-h-[44px] px-4 py-2.5 rounded-xl bg-emerald-700 text-white font-black text-xs hover:bg-emerald-800 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40"
                                >
                                    <i v-if="finishForm.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                                    <i v-else class="fa-solid fa-check text-emerald-300"></i>
                                    <span>Terminar llenado</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- DOS ACCIONES INDEPENDIENTES Y CLARAS -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- ========================================================= -->
                <!-- ACCIÓN 1: RECIBIR VACÍOS                                  -->
                <!-- ========================================================= -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center font-black text-sm shrink-0">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-black text-slate-900">
                                1. Recibir vacíos del camión
                            </h2>
                            <p class="text-xs text-slate-500">
                                Coteja y confirma la descarga física de botellones vacíos
                            </p>
                        </div>
                    </div>

                    <!-- Estado vacío si no hay entregas de chofer pendientes -->
                    <div v-if="pendingExchangeReceptions.length === 0" class="py-10 text-center text-slate-400">
                        <i class="fa-solid fa-check-circle text-3xl mb-2 text-slate-300"></i>
                        <p class="text-sm font-semibold">No hay entregas de vacíos pendientes de cotejo.</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Cuando el repartidor declare la entrega de vacíos en su pantalla «Mi carga», aparecerá aquí.
                        </p>
                    </div>

                    <!-- Lista de entregas de vacíos declaradas para cotejar -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="item in pendingExchangeReceptions"
                            :key="item.movement_id"
                            class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200/80 space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-black text-blue-900 bg-white px-2 py-0.5 rounded border border-slate-200 shadow-2xs">
                                        {{ item.reference }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700 ml-2">
                                        {{ item.driver_name }}
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">{{ item.created_at }}</span>
                            </div>

                            <!-- Cotejo numérico de vacíos -->
                            <div class="flex items-center justify-between gap-3 pt-2">
                                <div>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase block">Declarados:</span>
                                    <span class="text-lg font-black text-slate-900">{{ item.quantity_declared }} vacíos</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-600">Llegaron físicamente:</span>
                                    <input
                                        type="number"
                                        min="0"
                                        v-model.number="initEmptyReceptionState(item.movement_id, item.quantity_declared).received_quantity"
                                        class="w-16 h-10 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-base"
                                    />
                                </div>
                            </div>

                            <!-- Campo de motivo si hay diferencia -->
                            <div
                                v-if="(Number(initEmptyReceptionState(item.movement_id, item.quantity_declared).received_quantity) + Number(initEmptyReceptionState(item.movement_id, item.quantity_declared).damaged_quantity)) !== item.quantity_declared"
                                class="p-3 bg-amber-50 rounded-xl border border-amber-200 space-y-1.5"
                            >
                                <span class="text-xs font-bold text-amber-900 block">
                                    Diferencia detectada. Indique el motivo:
                                </span>
                                <input
                                    type="text"
                                    v-model="initEmptyReceptionState(item.movement_id, item.quantity_declared).difference_reason"
                                    placeholder="Ej: Chofer dejó 1 botellón olvidado en el camión..."
                                    required
                                    class="w-full text-xs rounded-lg border-amber-300 bg-white focus:ring-amber-500 text-slate-800"
                                />
                            </div>

                            <!-- Botón Confirmar recepción de vacíos -->
                            <div class="pt-2 flex justify-end">
                                <button
                                    type="button"
                                    @click="submitConfirmExchange(item)"
                                    :disabled="submittingReception[item.movement_id]"
                                    class="min-h-[44px] px-5 py-2.5 rounded-xl bg-blue-900 text-white font-black text-xs hover:bg-blue-800 active:scale-95 transition shadow-2xs flex items-center gap-2 cursor-pointer disabled:opacity-50"
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
                <!-- ACCIÓN 2: ENVIAR LLENOS AL CAMIÓN                         -->
                <!-- ========================================================= -->
                <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-5">
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center font-black text-sm shrink-0">
                            <i class="fa-solid fa-truck-arrow-right"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-black text-slate-900">
                                2. Enviar cambios llenos al camión
                            </h2>
                            <p class="text-xs text-slate-500">
                                Entrega botellones llenos listos · Se reservan en la propuesta
                            </p>
                        </div>
                    </div>

                    <!-- Formulario táctil de entrega de llenos -->
                    <form @submit.prevent="submitSendFull" class="space-y-5">
                        <div class="flex flex-col items-center justify-center py-4 bg-slate-50/70 rounded-2xl border border-slate-100 p-4 space-y-4">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Cantidad de cambios llenos a entregar (20L)
                            </label>

                            <!-- Control táctil grande (-) y (+) -->
                            <div class="flex items-center justify-center gap-4 w-full max-w-xs">
                                <button
                                    type="button"
                                    @click="sendDecrement(1)"
                                    :disabled="sendForm.quantity <= 1 || sendForm.processing"
                                    class="h-12 w-12 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 font-black text-xl flex items-center justify-center cursor-pointer shadow-2xs disabled:opacity-30"
                                >-</button>

                                <input
                                    type="number"
                                    min="1"
                                    :max="balances.exchange_full_ready"
                                    v-model.number="sendForm.quantity"
                                    class="w-24 h-14 text-center text-3xl font-black text-slate-900 bg-white border-2 border-blue-900/30 rounded-xl shadow-inner"
                                />

                                <button
                                    type="button"
                                    @click="sendIncrement(1)"
                                    :disabled="sendForm.quantity >= balances.exchange_full_ready || sendForm.processing"
                                    class="h-12 w-12 rounded-xl bg-blue-900 text-white hover:bg-blue-800 font-black text-xl flex items-center justify-center cursor-pointer shadow-2xs disabled:opacity-30"
                                >+</button>
                            </div>

                            <!-- Botones rápidos -->
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <button
                                    v-for="qty in [1, 5, 10, 15, 20]"
                                    :key="qty"
                                    type="button"
                                    @click="sendForm.quantity = Math.min(qty, balances.exchange_full_ready || 1)"
                                    class="min-h-[38px] px-3 py-1.5 rounded-lg text-xs font-bold bg-white border border-slate-200 hover:bg-slate-100 cursor-pointer shadow-2xs"
                                >
                                    {{ qty }}
                                </button>
                            </div>

                            <p class="text-[11px] text-slate-500 text-center">
                                Disponibles en almacén: <span class="font-bold text-emerald-700">{{ balances.exchange_full_ready }} botellones</span>
                            </p>
                        </div>

                        <!-- Botón Enviar Llenos al Camión -->
                        <button
                            type="submit"
                            :disabled="sendForm.processing || balances.exchange_full_ready <= 0 || sendForm.quantity <= 0"
                            class="w-full min-h-[50px] rounded-2xl bg-blue-900 hover:bg-blue-800 text-white font-black text-sm sm:text-base flex items-center justify-center gap-2 shadow-md hover:shadow-lg active:scale-[0.99] transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <i v-if="sendForm.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                            <i v-else class="fa-solid fa-paper-plane text-sky-400"></i>
                            <span>Enviar {{ sendForm.quantity }} lleno{{ sendForm.quantity > 1 ? 's' : '' }} al repartidor</span>
                        </button>
                    </form>

                    <!-- Propuestas pendientes de aceptación por el chofer -->
                    <div v-if="pendingExchangeProposals.length > 0" class="pt-3 border-t border-slate-100 space-y-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                            Propuestas enviadas pendientes de confirmar por el chofer
                        </span>

                        <div
                            v-for="prop in pendingExchangeProposals"
                            :key="prop.id"
                            class="p-3 bg-amber-50/70 rounded-xl border border-amber-200/70 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black">
                                    {{ prop.quantity }}
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">{{ prop.quantity }} cambios llenos enviados</p>
                                    <p class="text-[10px] text-slate-500">Pendiente de confirmación en «Mi carga»</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="withdrawProposal(prop.id)"
                                class="text-xs font-bold text-rose-600 hover:text-rose-800 px-2 py-1 rounded cursor-pointer"
                                title="Cancelar propuesta"
                            >
                                Retirar
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- HISTORIAL DE MOVIMIENTOS RECIENTES DE CAMBIOS -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-400 text-sm"></i>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                            Movimientos recientes de cambios
                        </h2>
                    </div>
                    <span class="text-xs text-slate-400 font-semibold">Vacíos recibidos y llenos enviados</span>
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
                                    'inline-flex items-center justify-center h-8 w-8 rounded-lg font-black shrink-0',
                                    mov.type === 'truck_unload_exchange'
                                        ? 'bg-blue-50 text-blue-900'
                                        : (mov.type === 'truck_load_exchange' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700')
                                ]"
                            >
                                {{ mov.quantity }}
                            </span>
                            <div>
                                <span class="font-bold text-slate-900 block">
                                    {{ mov.type === 'truck_unload_exchange' ? 'Camión → Planta (Vacíos recibidos)' : (mov.type === 'truck_load_exchange' ? 'Planta → Camión (Llenos enviados)' : 'Producción interna') }}
                                </span>
                                <span class="text-slate-400 text-[11px]">
                                    {{ mov.date }} · Chofer: {{ mov.driver_name }} · Operador: {{ mov.operator_name }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right self-start sm:self-auto">
                            <span
                                :class="[
                                    'inline-block px-2.5 py-1 rounded-full text-[10px] font-black',
                                    mov.difference_reason ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700'
                                ]"
                            >
                                {{ mov.difference_reason ? 'Aceptado con aclaración' : 'Confirmado' }}
                            </span>
                            <span v-if="mov.difference_reason" class="block text-[10px] text-amber-700 max-w-xs mt-0.5 truncate">
                                {{ mov.difference_reason }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: CONTEO INICIAL -->
            <div
                v-if="showInitialCountModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            >
                <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 sm:p-8 max-w-md w-full space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h2 class="text-base font-black text-slate-900">
                                Conteo Físico Inicial
                            </h2>
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
                                Botellones vacíos disponibles (20L):
                            </label>
                            <input
                                type="number"
                                min="0"
                                v-model.number="initialCountForm.empty_count"
                                required
                                class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300 focus:ring-amber-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                                Botellones llenos disponibles (20L):
                            </label>
                            <input
                                type="number"
                                min="0"
                                v-model.number="initialCountForm.full_count"
                                required
                                class="w-full h-11 text-lg font-black text-slate-900 rounded-xl border-slate-300 focus:ring-amber-500"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-600 mb-1">
                                Observaciones:
                            </label>
                            <input
                                type="text"
                                v-model="initialCountForm.notes"
                                class="w-full text-xs rounded-xl border-slate-300 focus:ring-amber-500 text-slate-800"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3">
                            <button
                                type="button"
                                @click="showInitialCountModal = false"
                                class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 cursor-pointer"
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

