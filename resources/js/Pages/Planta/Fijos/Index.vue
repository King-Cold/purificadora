<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

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
    fixedPendingPrepare: {
        type: Array,
        default: () => [],
    },
    fixedInWashing: {
        type: Array,
        default: () => [],
    },
    fixedPrepared: {
        type: Array,
        default: () => [],
    },
    fixedReadyToLoad: {
        type: Array,
        default: () => [],
    },
    pendingFixedProposals: {
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

// Pestaña actual reactiva
const currentTab = ref(props.activeTab || 'por-recibir');

const setTab = (tab) => {
    currentTab.value = tab;
    router.get(route('planta.fijos.index'), { tab }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// =========================================================================
// TAB 1: POR RECIBIR (Cotejo físico presencial de fijos declarados por chofer)
// =========================================================================
// Estado local por cada pedido/renglón para cotejo táctil
const receptionStates = ref({});

const initReceptionState = (itemId, pendingQty) => {
    if (!receptionStates.value[itemId]) {
        receptionStates.value[itemId] = {
            checked: false,
            received_quantity: 0,
            pending_quantity: pendingQty,
            damaged_quantity: 0,
            damage_type: 'rotura',
            damage_notes: '',
            show_damaged: false,
            difference_reason: '',
            start_washing: false,
        };
    }
    return receptionStates.value[itemId];
};

// "Marcar todos los recibidos" para el pedido abierto
const markAllReceived = (item) => {
    const st = initReceptionState(item.order_item_id, item.pending_quantity);
    st.checked = true;
    st.received_quantity = item.pending_quantity;
    st.difference_reason = '';
};

// Alternar checkbox individual
const toggleCheck = (item) => {
    const st = initReceptionState(item.order_item_id, item.pending_quantity);
    if (!st.checked) {
        st.checked = true;
        st.received_quantity = item.pending_quantity;
    } else {
        st.checked = false;
        st.received_quantity = 0;
    }
};

const submittingReception = ref({});

const submitReception = (item) => {
    const st = initReceptionState(item.order_item_id, item.pending_quantity);
    const received = Number(st.received_quantity) || 0;
    const damaged = Number(st.damaged_quantity) || 0;
    const pending = item.pending_quantity;

    // Validación de faltante
    if ((received + damaged) < pending && !st.difference_reason?.trim()) {
        alert('Debe especificar el motivo del faltante de botellones fijos.');
        return;
    }

    submittingReception.value[item.order_item_id] = true;

    router.post(route('planta.reception.confirm-fixed'), {
        order_item_id: item.order_item_id,
        received_quantity: received,
        start_washing: Boolean(st.start_washing),
        damaged_quantity: damaged,
        damage_type: damaged > 0 ? st.damage_type : null,
        damage_notes: damaged > 0 ? st.damage_notes : null,
        difference_reason: (received + damaged) < pending ? st.difference_reason : null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete receptionStates.value[item.order_item_id];
            submittingReception.value[item.order_item_id] = false;
        },
        onError: () => {
            submittingReception.value[item.order_item_id] = false;
        },
        onFinish: () => {
            submittingReception.value[item.order_item_id] = false;
        },
    });
};

// =========================================================================
// TAB 2: POR LLENAR (Lavado y llenado de fijos aptos recibidos)
// =========================================================================
const washingStates = ref({});

const initWashingState = (itemId, inWashingQty) => {
    if (!washingStates.value[itemId]) {
        washingStates.value[itemId] = {
            checked: false,
            finish_quantity: 0,
            max_quantity: inWashingQty,
        };
    }
    return washingStates.value[itemId];
};

const markAllFilled = (item) => {
    const st = initWashingState(item.item_id, item.in_washing_quantity);
    st.checked = true;
    st.finish_quantity = item.in_washing_quantity;
};

const toggleWashingCheck = (item) => {
    const st = initWashingState(item.item_id, item.in_washing_quantity);
    if (!st.checked) {
        st.checked = true;
        st.finish_quantity = item.in_washing_quantity;
    } else {
        st.checked = false;
        st.finish_quantity = 0;
    }
};

const submittingWashing = ref({});

// Iniciar lavado para fijos pendientes
const submitStartWash = (item, qty) => {
    submittingWashing.value[item.item_id] = true;
    router.post(route('planta.production.fixed.start'), {
        order_item_id: item.item_id,
        quantity: qty || item.pending_to_wash_quantity,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submittingWashing.value[item.item_id] = false;
        },
    });
};

// Confirmar llenado (finalizar preparación)
const submitFinishWash = (item) => {
    const st = initWashingState(item.item_id, item.in_washing_quantity);
    const qty = Number(st.finish_quantity) || (st.checked ? item.in_washing_quantity : 0);

    if (qty <= 0) {
        alert('Indique la cantidad de botellones llenados para confirmar.');
        return;
    }

    submittingWashing.value[item.item_id] = true;
    router.post(route('planta.production.fixed.finish'), {
        order_item_id: item.item_id,
        quantity: qty,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete washingStates.value[item.item_id];
            submittingWashing.value[item.item_id] = false;
        },
        onError: () => {
            submittingWashing.value[item.item_id] = false;
        },
        onFinish: () => {
            submittingWashing.value[item.item_id] = false;
        },
    });
};

// =========================================================================
// TAB 3: LISTOS PARA ENVIAR (Propuesta de carga al camión)
// =========================================================================
const loadStates = ref({});

const initLoadState = (itemId, readyQty) => {
    if (!loadStates.value[itemId]) {
        loadStates.value[itemId] = {
            quantity: readyQty,
            notes: '',
        };
    }
    return loadStates.value[itemId];
};

const submittingLoad = ref({});

const submitSendToDriver = (item) => {
    const st = initLoadState(item.item_id, item.ready_to_load_quantity);
    const qty = Number(st.quantity) || item.ready_to_load_quantity;

    if (qty <= 0) {
        alert('Indique la cantidad de botellones a enviar al camión.');
        return;
    }

    submittingLoad.value[item.item_id] = true;
    router.post(route('planta.loads.store'), {
        bottle_type: 'fijo',
        order_item_id: item.item_id,
        quantity: qty,
        notes: st.notes || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete loadStates.value[item.item_id];
            submittingLoad.value[item.item_id] = false;
        },
        onError: () => {
            submittingLoad.value[item.item_id] = false;
        },
        onFinish: () => {
            submittingLoad.value[item.item_id] = false;
        },
    });
};

// Retirar propuesta no aceptada
const withdrawProposal = (proposalId) => {
    if (!confirm('¿Desea retirar esta propuesta de carga de fijos?')) return;
    router.post(route('planta.loads.withdraw', proposalId), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Planta · Fijos del Repartidor" />

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

            <!-- ENCABEZADO Y TRES PESTAÑAS PRINCIPALES -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-blue-900 text-white flex items-center justify-center shadow-2xs shrink-0">
                            <i class="fa-solid fa-boxes-stacked text-xl text-sky-400"></i>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Botellones Fijos
                            </h1>
                            <p class="text-xs sm:text-sm font-medium text-slate-500">
                                Gestión por propietario · 20L · Planta → Repartidor → Cliente
                            </p>
                        </div>
                    </div>

                    <!-- Modo supervisor aviso -->
                    <span
                        v-if="isAdmin && !isOperator"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold"
                    >
                        <i class="fa-solid fa-eye text-amber-600"></i>
                        <span>Modo Supervisión</span>
                    </span>
                </div>

                <!-- SELECTOR DE PESTAÑAS TÁCTIL (≥ 44 px de altura) -->
                <div class="grid grid-cols-3 gap-2 p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/60">
                    <!-- Pestaña 1: Por recibir -->
                    <button
                        type="button"
                        @click="setTab('por-recibir')"
                        :class="[
                            'min-h-[48px] py-2.5 px-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                            currentTab === 'por-recibir'
                                ? 'bg-white text-blue-900 shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                        ]"
                    >
                        <i class="fa-solid fa-dolly text-sm"></i>
                        <span>Por recibir</span>
                        <span
                            v-if="fixedPendingCount > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-black bg-amber-500 text-white"
                        >
                            {{ fixedPendingCount }}
                        </span>
                    </button>

                    <!-- Pestaña 2: Por llenar -->
                    <button
                        type="button"
                        @click="setTab('por-llenar')"
                        :class="[
                            'min-h-[48px] py-2.5 px-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                            currentTab === 'por-llenar'
                                ? 'bg-white text-blue-900 shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'
                        ]"
                    >
                        <i class="fa-solid fa-soap text-sm"></i>
                        <span>Por llenar</span>
                        <span
                            v-if="(fixedPendingPrepare.length + fixedInWashing.length) > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-black bg-blue-100 text-blue-900"
                        >
                            {{ fixedPendingPrepare.length + fixedInWashing.length }}
                        </span>
                    </button>

                    <!-- Pestaña 3: Listos para enviar -->
                    <button
                        type="button"
                        @click="setTab('listos-para-enviar')"
                        :class="[
                            'min-h-[48px] py-2.5 px-2 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition cursor-pointer',
                            currentTab === 'listos-para-enviar'
                                ? 'bg-white text-blue-900 shadow-2xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                        ]"
                    >
                        <i class="fa-solid fa-truck-ramp-box text-sm"></i>
                        <span>Listos para enviar</span>
                        <span
                            v-if="fixedReadyToLoad.length > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800"
                        >
                            {{ fixedReadyToLoad.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- CONTENIDO DE PESTAÑA 1: POR RECIBIR                           -->
            <!-- ============================================================= -->
            <div v-if="currentTab === 'por-recibir'" class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Fijos declarados por el Repartidor pendientes de cotejo físico
                    </p>
                    <span class="text-xs font-bold text-slate-400">
                        {{ fixedPending.length }} pedido{{ fixedPending.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <!-- Estado vacío -->
                <div v-if="fixedPending.length === 0" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-10 text-center text-slate-500">
                    <i class="fa-solid fa-clipboard-check text-4xl text-slate-300 mb-3 block"></i>
                    <h3 class="text-base font-bold text-slate-800">No hay fijos pendientes de recibir</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        Cuando el repartidor recoja envases fijos y declare su llegada a Planta, aparecerán aquí para su cotejo presencial.
                    </p>
                </div>

                <!-- Lista de pedidos con fijos pendientes -->
                <div v-else class="space-y-4">
                    <div
                        v-for="item in fixedPending"
                        :key="item.order_item_id"
                        class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-5 sm:p-6 space-y-4 transition hover:border-blue-900/40"
                    >
                        <!-- Fila de identificación del cliente y pedido -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-blue-900 bg-blue-50 px-2.5 py-1 rounded-lg">
                                        {{ item.order_folio }}
                                    </span>
                                    <span v-if="item.customer_code" class="text-xs font-mono font-bold text-slate-400">
                                        {{ item.customer_code }}
                                    </span>
                                </div>
                                <h3 class="text-base font-black text-slate-900 mt-1">
                                    {{ item.customer_nickname || item.customer_name }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Declarado por: <span class="font-semibold text-slate-700">{{ item.driver_name }}</span> · {{ item.created_at }}
                                </p>
                            </div>

                            <!-- Botones rápidos de marcado por pedido -->
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="markAllReceived(item)"
                                    class="min-h-[44px] px-3.5 py-2 rounded-xl bg-blue-50 text-blue-900 hover:bg-blue-100 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer"
                                    title="Marcar todos los esperados como recibidos físicamente"
                                >
                                    <i class="fa-solid fa-check-double text-sky-600"></i>
                                    <span>Marcar todos los recibidos ({{ item.pending_quantity }})</span>
                                </button>
                            </div>
                        </div>

                        <!-- Panel de cotejo físico por pedido -->
                        <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200/70 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <!-- Cantidad esperada vs recibida -->
                                <div class="flex items-center gap-4">
                                    <!-- Casilla táctil individual (empieza desmarcada) -->
                                    <label class="flex items-center gap-3 cursor-pointer select-none">
                                        <input
                                            type="checkbox"
                                            :checked="initReceptionState(item.order_item_id, item.pending_quantity).checked"
                                            @change="toggleCheck(item)"
                                            class="h-6 w-6 rounded-lg text-blue-900 border-slate-300 focus:ring-blue-900/20 cursor-pointer"
                                        />
                                        <div>
                                            <span class="text-xs font-bold uppercase text-slate-400 block">Declarados en camino</span>
                                            <span class="text-lg font-black text-slate-900">{{ item.pending_quantity }} fijos (20L)</span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Ajuste numérico presencial (si llegaron menos o más) -->
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold text-slate-600">Llegaron físicamente:</span>
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="() => {
                                                const st = initReceptionState(item.order_item_id, item.pending_quantity);
                                                st.received_quantity = Math.max(0, (Number(st.received_quantity) || 0) - 1);
                                                st.checked = st.received_quantity > 0;
                                            }"
                                            class="h-10 w-10 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 flex items-center justify-center cursor-pointer shadow-2xs"
                                        >-</button>
                                        <input
                                            type="number"
                                            min="0"
                                            :max="item.pending_quantity"
                                            v-model.number="initReceptionState(item.order_item_id, item.pending_quantity).received_quantity"
                                            @input="() => {
                                                const st = initReceptionState(item.order_item_id, item.pending_quantity);
                                                st.checked = st.received_quantity > 0;
                                            }"
                                            class="w-16 h-10 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-base focus:ring-2 focus:ring-blue-900/20"
                                        />
                                        <button
                                            type="button"
                                            @click="() => {
                                                const st = initReceptionState(item.order_item_id, item.pending_quantity);
                                                st.received_quantity = Math.min(item.pending_quantity, (Number(st.received_quantity) || 0) + 1);
                                                st.checked = true;
                                            }"
                                            class="h-10 w-10 rounded-xl bg-blue-900 text-white font-bold hover:bg-blue-800 flex items-center justify-center cursor-pointer shadow-2xs"
                                        >+</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Si esperaban 3 y llegaron 2: pide motivo del faltante -->
                            <div
                                v-if="(Number(initReceptionState(item.order_item_id, item.pending_quantity).received_quantity) + Number(initReceptionState(item.order_item_id, item.pending_quantity).damaged_quantity)) < item.pending_quantity"
                                class="p-3 bg-amber-50 rounded-xl border border-amber-200 space-y-1.5"
                            >
                                <div class="flex items-center gap-2 text-amber-800 font-bold text-xs">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    <span>Hay un faltante de {{ item.pending_quantity - (Number(initReceptionState(item.order_item_id, item.pending_quantity).received_quantity) + Number(initReceptionState(item.order_item_id, item.pending_quantity).damaged_quantity)) }} botellón(es). Indique el motivo:</span>
                                </div>
                                <input
                                    type="text"
                                    v-model="initReceptionState(item.order_item_id, item.pending_quantity).difference_reason"
                                    placeholder="Ej: Chofer reporta que el cliente no entregó el tercer botellón..."
                                    required
                                    class="w-full text-xs rounded-lg border-amber-300 bg-white focus:ring-2 focus:ring-amber-500/20 text-slate-800"
                                />
                            </div>

                            <!-- Opciones: Unidades dañadas al recibir -->
                            <div class="pt-2 border-t border-slate-200/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="initReceptionState(item.order_item_id, item.pending_quantity).start_washing"
                                        class="rounded text-blue-900 border-slate-300 focus:ring-blue-900/20"
                                    />
                                    <span>Ingresar de inmediato a lavado y llenado</span>
                                </label>

                                <button
                                    type="button"
                                    @click="initReceptionState(item.order_item_id, item.pending_quantity).show_damaged = !initReceptionState(item.order_item_id, item.pending_quantity).show_damaged"
                                    class="text-xs font-bold text-amber-700 hover:text-amber-800 flex items-center gap-1.5 cursor-pointer self-start sm:self-auto"
                                >
                                    <i class="fa-solid fa-flask-vial"></i>
                                    <span>{{ initReceptionState(item.order_item_id, item.pending_quantity).show_damaged ? 'Ocultar daño' : '¿Llegó algún botellón dañado?' }}</span>
                                </button>
                            </div>

                            <!-- Sub-formulario para registrar daño/incidencia física -->
                            <div
                                v-if="initReceptionState(item.order_item_id, item.pending_quantity).show_damaged"
                                class="p-3 bg-rose-50/70 rounded-xl border border-rose-200 space-y-3"
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <label class="block font-bold text-rose-900 mb-1">Cantidad con daño:</label>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="item.pending_quantity"
                                            v-model.number="initReceptionState(item.order_item_id, item.pending_quantity).damaged_quantity"
                                            class="w-full text-xs rounded-lg border-rose-300 bg-white focus:ring-rose-500"
                                        />
                                    </div>
                                    <div>
                                        <label class="block font-bold text-rose-900 mb-1">Tipo de daño:</label>
                                        <select
                                            v-model="initReceptionState(item.order_item_id, item.pending_quantity).damage_type"
                                            class="w-full text-xs rounded-lg border-rose-300 bg-white focus:ring-rose-500"
                                        >
                                            <option value="rotura">Rotura / Quebrado</option>
                                            <option value="fisura">Fisura / Fuga</option>
                                            <option value="contaminacion">Contaminación interna</option>
                                            <option value="deformacion">Deformación por calor/golpe</option>
                                            <option value="otro">Otro daño</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <input
                                        type="text"
                                        v-model="initReceptionState(item.order_item_id, item.pending_quantity).damage_notes"
                                        placeholder="Descripción del daño detectado al cotejar..."
                                        class="w-full text-xs rounded-lg border-rose-300 bg-white focus:ring-rose-500"
                                    />
                                </div>
                            </div>

                            <!-- Botón de Confirmación Presencial -->
                            <div class="pt-2 flex justify-end">
                                <button
                                    type="button"
                                    @click="submitReception(item)"
                                    :disabled="submittingReception[item.order_item_id] || (!initReceptionState(item.order_item_id, item.pending_quantity).checked && initReceptionState(item.order_item_id, item.pending_quantity).received_quantity === 0)"
                                    class="min-h-[44px] px-6 py-2.5 rounded-xl bg-blue-900 text-white font-black text-xs sm:text-sm hover:bg-blue-800 active:scale-95 transition shadow-2xs flex items-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                >
                                    <i v-if="submittingReception[item.order_item_id]" class="fa-solid fa-circle-notch fa-spin"></i>
                                    <i v-else class="fa-solid fa-circle-check text-sky-400"></i>
                                    <span>Confirmar recepción física</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- CONTENIDO DE PESTAÑA 2: POR LLENAR (Lavado y preparación)     -->
            <!-- ============================================================= -->
            <div v-else-if="currentTab === 'por-llenar'" class="space-y-6">
                <!-- Subsección 1: Fijos pendientes de iniciar lavado -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            1. Recibidos en Planta · Pendientes de ingresar a lavado
                        </p>
                        <span class="text-xs font-bold text-slate-400">
                            {{ fixedPendingPrepare.length }} pedido{{ fixedPendingPrepare.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="fixedPendingPrepare.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-5 text-center text-xs text-slate-400">
                        No hay fijos pendientes de iniciar lavado.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in fixedPendingPrepare"
                            :key="`prep-${item.item_id}`"
                            class="bg-white rounded-2xl border border-slate-200/90 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
                        >
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-800 flex items-center justify-center font-black text-sm shrink-0">
                                    {{ item.pending_to_wash_quantity }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-blue-900 bg-blue-50 px-2 py-0.5 rounded">
                                            {{ item.order_folio }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900">
                                            {{ item.customer_nickname || item.customer_name }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Disponibles para lavar: <span class="font-bold text-slate-800">{{ item.pending_to_wash_quantity }} botellones</span>
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="submitStartWash(item)"
                                :disabled="submittingWashing[item.item_id]"
                                class="min-h-[44px] px-4 py-2 rounded-xl bg-blue-900 text-white font-bold text-xs hover:bg-blue-800 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer shadow-2xs self-start sm:self-auto"
                            >
                                <i v-if="submittingWashing[item.item_id]" class="fa-solid fa-circle-notch fa-spin"></i>
                                <i v-else class="fa-solid fa-soap text-sky-400"></i>
                                <span>Iniciar lavado ({{ item.pending_to_wash_quantity }})</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Subsección 2: Fijos en lavado y llenado (mesa de trabajo) -->
                <div class="space-y-3 pt-4 border-t border-slate-200/80">
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            2. En línea de lavado y llenado · Finalizar preparación
                        </p>
                        <span class="text-xs font-bold text-slate-400">
                            {{ fixedInWashing.length }} en proceso
                        </span>
                    </div>

                    <div v-if="fixedInWashing.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-6 text-center text-xs text-slate-400">
                        No hay botellones fijos en proceso de lavado y llenado en este momento.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="item in fixedInWashing"
                            :key="`wash-${item.item_id}`"
                            class="bg-white rounded-2xl border border-slate-200/90 p-5 space-y-4 shadow-2xs hover:border-blue-900/40 transition"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-blue-900 bg-blue-50 px-2.5 py-1 rounded-lg">
                                            {{ item.order_folio }}
                                        </span>
                                        <span class="text-xs font-black text-slate-900">
                                            {{ item.customer_nickname || item.customer_name }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">
                                        En lavado y purificación: <span class="font-black text-slate-800">{{ item.in_washing_quantity }} botellones</span>
                                    </p>
                                </div>

                                <!-- Botón Marcar todos como llenados -->
                                <button
                                    type="button"
                                    @click="markAllFilled(item)"
                                    class="min-h-[44px] px-3.5 py-2 rounded-xl bg-blue-50 text-blue-900 hover:bg-blue-100 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer self-start sm:self-auto"
                                >
                                    <i class="fa-solid fa-check-double text-sky-600"></i>
                                    <span>Marcar todos como llenados ({{ item.in_washing_quantity }})</span>
                                </button>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/70 p-3.5 rounded-xl border border-slate-200/60">
                                <label class="flex items-center gap-3 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        :checked="initWashingState(item.item_id, item.in_washing_quantity).checked"
                                        @change="toggleWashingCheck(item)"
                                        class="h-6 w-6 rounded-lg text-blue-900 border-slate-300 focus:ring-blue-900/20 cursor-pointer"
                                    />
                                    <div>
                                        <span class="text-xs font-bold text-slate-700">Listo para pasar a Listos para enviar</span>
                                        <span class="block text-[11px] text-slate-400">Lavado, llenado y sellado concluido</span>
                                    </div>
                                </label>

                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-semibold text-slate-500">Cantidad terminada:</span>
                                    <input
                                        type="number"
                                        min="1"
                                        :max="item.in_washing_quantity"
                                        v-model.number="initWashingState(item.item_id, item.in_washing_quantity).finish_quantity"
                                        class="w-16 h-10 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-sm"
                                    />

                                    <button
                                        type="button"
                                        @click="submitFinishWash(item)"
                                        :disabled="submittingWashing[item.item_id] || initWashingState(item.item_id, item.in_washing_quantity).finish_quantity <= 0"
                                        class="min-h-[44px] px-5 py-2 rounded-xl bg-blue-900 text-white font-black text-xs hover:bg-blue-800 active:scale-95 transition flex items-center gap-2 cursor-pointer shadow-2xs disabled:opacity-40 disabled:cursor-not-allowed"
                                    >
                                        <i v-if="submittingWashing[item.item_id]" class="fa-solid fa-circle-notch fa-spin"></i>
                                        <i v-else class="fa-solid fa-check text-sky-400"></i>
                                        <span>Confirmar llenado</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================= -->
            <!-- CONTENIDO DE PESTAÑA 3: LISTOS PARA ENVIAR                    -->
            <!-- ============================================================= -->
            <div v-else-if="currentTab === 'listos-para-enviar'" class="space-y-6">
                <!-- Fijos preparados listos para entregar al camión -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Fijos listos en almacén de Planta para proponer al camión
                        </p>
                        <span class="text-xs font-bold text-slate-400">
                            {{ fixedReadyToLoad.length }} orden{{ fixedReadyToLoad.length === 1 ? '' : 'es' }}
                        </span>
                    </div>

                    <!-- Estado vacío -->
                    <div v-if="fixedReadyToLoad.length === 0" class="bg-white rounded-3xl border border-slate-200/90 shadow-2xs p-8 text-center text-slate-400">
                        <i class="fa-solid fa-boxes-packing text-3xl mb-2 text-slate-300"></i>
                        <p class="text-sm font-semibold">No hay botellones fijos preparados en almacén de listos.</p>
                        <p class="text-xs text-slate-400">Lava y llena los fijos recibidos en la pestaña «Por llenar» para tener unidades disponibles para enviar.</p>
                    </div>

                    <!-- Lista de fijos preparados con selector táctil -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="item in fixedReadyToLoad"
                            :key="item.item_id"
                            class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-blue-900/40 transition"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-black text-blue-900 bg-blue-50 px-2.5 py-1 rounded-lg">
                                        {{ item.order_folio }}
                                    </span>
                                    <span v-if="item.customer_code" class="text-xs font-mono font-bold text-slate-400">
                                        {{ item.customer_code }}
                                    </span>
                                </div>
                                <h3 class="text-base font-black text-slate-900 mt-1">
                                    {{ item.customer_nickname || item.customer_name }}
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Preparados disponibles: <span class="font-black text-emerald-700">{{ item.ready_to_load_quantity }} botellones</span>
                                </p>
                            </div>

                            <!-- Selector de cantidad y botón Enviar al Repartidor -->
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="() => {
                                            const st = initLoadState(item.item_id, item.ready_to_load_quantity);
                                            st.quantity = Math.max(1, (Number(st.quantity) || 1) - 1);
                                        }"
                                        class="h-10 w-10 rounded-xl bg-slate-100 text-slate-700 font-bold hover:bg-slate-200 flex items-center justify-center cursor-pointer shadow-2xs"
                                    >-</button>
                                    <input
                                        type="number"
                                        min="1"
                                        :max="item.ready_to_load_quantity"
                                        v-model.number="initLoadState(item.item_id, item.ready_to_load_quantity).quantity"
                                        class="w-16 h-10 text-center font-black text-slate-900 bg-white border border-slate-200 rounded-xl text-sm"
                                    />
                                    <button
                                        type="button"
                                        @click="() => {
                                            const st = initLoadState(item.item_id, item.ready_to_load_quantity);
                                            st.quantity = Math.min(item.ready_to_load_quantity, (Number(st.quantity) || 1) + 1);
                                        }"
                                        class="h-10 w-10 rounded-xl bg-blue-900 text-white font-bold hover:bg-blue-800 flex items-center justify-center cursor-pointer shadow-2xs"
                                    >+</button>
                                </div>

                                <button
                                    type="button"
                                    @click="submitSendToDriver(item)"
                                    :disabled="submittingLoad[item.item_id]"
                                    class="min-h-[44px] px-5 py-2.5 rounded-xl bg-blue-900 text-white font-black text-xs sm:text-sm hover:bg-blue-800 active:scale-95 transition flex items-center gap-2 cursor-pointer shadow-2xs disabled:opacity-50"
                                >
                                    <i v-if="submittingLoad[item.item_id]" class="fa-solid fa-circle-notch fa-spin"></i>
                                    <i v-else class="fa-solid fa-truck-arrow-right text-sky-400"></i>
                                    <span>Enviar al repartidor</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Propuestas pendientes de confirmación por el Repartidor -->
                <div class="space-y-3 pt-4 border-t border-slate-200/80">
                    <div class="flex items-center justify-between px-1">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Propuestas de fijos pendientes de aceptación física en camión
                        </p>
                        <span class="text-xs font-bold text-slate-400">
                            {{ pendingFixedProposals.length }} propuesta{{ pendingFixedProposals.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="pendingFixedProposals.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-5 text-center text-xs text-slate-400">
                        No hay propuestas de fijos pendientes de confirmación por el chofer.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="prop in pendingFixedProposals"
                            :key="`prop-${prop.id}`"
                            class="bg-amber-50/60 rounded-2xl border border-amber-200/80 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs"
                        >
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-black text-sm shrink-0">
                                    {{ prop.quantity }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-blue-900 bg-white px-2 py-0.5 rounded shadow-2xs">
                                            {{ prop.order_folio }}
                                        </span>
                                        <span class="text-xs font-bold text-slate-900">
                                            {{ prop.customer_nickname || prop.customer_name }}
                                        </span>
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-200 text-amber-900">
                                            Pendiente de confirmación
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 mt-0.5">
                                        Propuesto por {{ prop.initiator_name }} · {{ prop.created_at }} · Esperando aceptación del chofer en «Mi carga».
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="withdrawProposal(prop.id)"
                                class="min-h-[40px] px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 text-rose-700 hover:bg-rose-50 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer self-start sm:self-auto shadow-2xs"
                                title="Retirar propuesta antes de que el repartidor acepte"
                            >
                                <i class="fa-solid fa-ban text-rose-500"></i>
                                <span>Retirar propuesta</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

