<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error || page.props.errors?.fill || page.props.errors?.received_quantity || null);

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
// ACCIONES: PAPELERA ROJA (CONFIRMACIÓN DE RETIRO / DERIVAR A INCIDENCIA)
// =========================================================================
const showTrashModal = ref(false);
const itemForTrash = ref(null);
const trashTab = ref('por-recibir');

const openTrashModal = (item, tab) => {
    itemForTrash.value = item;
    trashTab.value = tab;
    showTrashModal.value = true;
};

const closeTrashModal = () => {
    showTrashModal.value = false;
    itemForTrash.value = null;
};

const handleTrashDeriveToIncident = () => {
    const item = itemForTrash.value;
    const tab = trashTab.value;
    closeTrashModal();
    if (tab === 'por-recibir') {
        openReceptionIncidentModal(item);
    } else {
        openFillIncidentModal(item);
    }
};

// =========================================================================
// PESTAÑA 1: POR RECIBIR
// =========================================================================

// Acción 3: Palomita verde (Aceptar recepción en un toque)
const submittingIndividual = ref({});
const handleAcceptSingleReception = (item) => {
    submittingIndividual.value[item.order_item_id] = true;
    router.post(route('planta.reception.confirm-fixed'), {
        order_item_id: item.order_item_id,
        received_quantity: item.expected_quantity,
        damaged_quantity: 0,
        difference_reason: null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            submittingIndividual.value[item.order_item_id] = false;
        },
    });
};

// Acción 2: Triángulo ámbar (Modal Incidencia en Recepción)
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
        alert('Debe especificar el motivo obligatorio de los faltantes (mínimo 5 caracteres).');
        return;
    }

    if (receptionIncidentForm.damaged_quantity > 0 && receptionIncidentForm.damage_notes.trim().length < 5) {
        alert('Debe describir el daño detectado en los envases (mínimo 5 caracteres).');
        return;
    }

    receptionIncidentForm.post(route('planta.reception.confirm-fixed'), {
        preserveScroll: true,
        onSuccess: () => closeReceptionIncidentModal(),
    });
};

// Modal Aceptar Todos (Por recibir)
const showAcceptAllModal = ref(false);
const acceptAllForm = useForm({});

const eligibleForAcceptAll = computed(() => {
    return props.fixedPending.filter(item => (item.damaged_quantity || 0) === 0);
});

const totalBottlesForAcceptAll = computed(() => {
    return eligibleForAcceptAll.value.reduce((acc, curr) => acc + (curr.expected_quantity || 0), 0);
});

const excludedFromAcceptAll = computed(() => {
    return props.fixedPending.filter(item => (item.damaged_quantity || 0) > 0);
});

const openAcceptAllModal = () => {
    showAcceptAllModal.value = true;
};

const closeAcceptAllModal = () => {
    showAcceptAllModal.value = false;
};

const submitAcceptAll = () => {
    acceptAllForm.post(route('planta.reception.confirm-all-fixed'), {
        preserveScroll: true,
        onSuccess: () => closeAcceptAllModal(),
    });
};

// =========================================================================
// PESTAÑA 2: POR LLENAR
// =========================================================================

// Acción 3: Palomita verde (Modal Marcar como llenados total o parcial)
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

// Acción 2: Triángulo ámbar (Modal Incidencia en Llenado)
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
        alert('Debe describir el daño detectado durante el lavado/llenado (mínimo 5 caracteres).');
        return;
    }

    fillIncidentForm.post(route('planta.fijos.report-incident'), {
        preserveScroll: true,
        onSuccess: () => closeFillIncidentModal(),
    });
};

// Modal Marcar Todos como Llenados
const showFillAllModal = ref(false);
const fillAllForm = useForm({});

const totalOrdersForFillAll = computed(() => props.fixedToFill.length);
const totalBottlesForFillAll = computed(() => {
    return props.fixedToFill.reduce((acc, curr) => acc + (curr.pending_quantity || 0), 0);
});

const openFillAllModal = () => {
    showFillAllModal.value = true;
};

const closeFillAllModal = () => {
    showFillAllModal.value = false;
};

const submitFillAll = () => {
    fillAllForm.post(route('planta.fijos.mark-all-filled'), {
        preserveScroll: true,
        onSuccess: () => closeFillAllModal(),
    });
};
</script>

<template>
    <Head title="Fijos en Planta" />

    <AuthenticatedLayout wide>
        <div class="max-w-4xl mx-auto px-2.5 sm:px-6 lg:px-8 py-3 sm:py-5 space-y-3 pb-24 md:pb-12">

            <!-- Mensajes Flash -->
            <div
                v-if="flashSuccess"
                class="p-3.5 sm:p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs sm:text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-circle-check text-emerald-600 text-base mt-0.5 shrink-0"></i>
                <div class="flex-1 font-semibold leading-relaxed">{{ flashSuccess }}</div>
            </div>

            <div
                v-if="flashError"
                class="p-3.5 sm:p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs sm:text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base mt-0.5 shrink-0"></i>
                <div class="flex-1 font-semibold leading-relaxed">{{ flashError }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO Y PESTAÑAS (POR RECIBIR / POR LLENAR)                       -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-4 sm:p-5">
                <div class="flex items-center justify-between pb-3">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                        <i class="fa-solid fa-industry text-blue-900"></i>
                        <span>Fijos en Planta</span>
                    </h1>
                </div>

                <!-- Pestañas Por recibir / Por llenar -->
                <div class="flex items-center gap-2 border-t border-slate-100 pt-3">
                    <!-- Tab 1: Por recibir -->
                    <button
                        @click="setTab('por-recibir')"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-black transition min-h-[44px] flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por-recibir'
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-dolly text-xs"></i>
                        <span>Por recibir</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black"
                            :class="currentTab === 'por-recibir' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ fixedPendingCount }}
                        </span>
                    </button>

                    <!-- Tab 2: Por llenar -->
                    <button
                        @click="setTab('por-llenar')"
                        type="button"
                        class="px-4 py-2.5 rounded-xl text-xs font-black transition min-h-[44px] flex items-center gap-2 border cursor-pointer"
                        :class="currentTab === 'por-llenar'
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <i class="fa-solid fa-faucet-drip text-xs"></i>
                        <span>Por llenar</span>
                        <span
                            class="px-2 py-0.5 rounded-full text-[10px] font-black"
                            :class="currentTab === 'por-llenar' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ fixedToFillCount }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. TABLA: POR RECIBIR                                                     -->
            <!-- ========================================================================= -->
            <div v-if="currentTab === 'por-recibir'" class="space-y-3">
                <!-- Acción masiva compacta encima de la tabla -->
                <div class="flex items-center justify-between gap-3 bg-white px-3 sm:px-4 py-2.5 rounded-xl border border-slate-200 shadow-2xs">
                    <span class="text-xs font-bold text-slate-600">
                        {{ fixedPending.length }} {{ fixedPending.length === 1 ? 'cliente pendiente' : 'clientes pendientes' }}
                    </span>

                    <button
                        v-if="fixedPending.length > 0"
                        @click="openAcceptAllModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white shadow-2xs transition active:scale-95 min-h-[44px] cursor-pointer"
                        title="Aceptar todos los fijos pendientes"
                        aria-label="Aceptar todos los fijos pendientes"
                    >
                        <i class="fa-solid fa-check-double text-sky-400"></i>
                        <span>Aceptar todos</span>
                    </button>
                </div>

                <!-- Tabla compacta (Cliente, Fijos, Acciones) -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div v-if="fixedPending.length === 0" class="p-12 text-center space-y-2">
                        <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <p class="text-sm font-bold text-slate-800">No hay fijos pendientes de recibir</p>
                        <p class="text-xs text-slate-400">Cuando el Repartidor confirme recogidas en ruta, aparecerán aquí.</p>
                    </div>

                    <table v-else class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                <th scope="col" class="py-3 px-3 sm:px-5">Cliente</th>
                                <th scope="col" class="py-3 px-2 sm:px-4 text-right">Fijos</th>
                                <th scope="col" class="py-3 px-3 sm:px-5 text-right w-[156px] sm:w-[168px]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in fixedPending"
                                :key="item.order_item_id"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <!-- 1. Cliente: Apodo en negrita sin comillas, nombre debajo si existe -->
                                <td class="py-3.5 px-3 sm:px-5 align-middle">
                                    <span class="font-bold text-slate-900 text-sm sm:text-base leading-tight block">
                                        {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                    </span>
                                    <span
                                        v-if="item.customer_nickname && item.customer_name"
                                        class="text-xs text-slate-500 font-normal mt-0.5 block"
                                    >
                                        {{ item.customer_name }}
                                    </span>
                                </td>

                                <!-- 2. Fijos: únicamente el número -->
                                <td class="py-3.5 px-2 sm:px-4 align-middle text-right">
                                    <span class="font-bold sm:font-black text-slate-900 text-base sm:text-lg font-mono">
                                        {{ item.expected_quantity }}
                                    </span>
                                </td>

                                <!-- 3. Acciones: Papelera roja, Triángulo ámbar, Palomita verde -->
                                <td class="py-3.5 px-3 sm:px-5 align-middle text-right">
                                    <div class="flex items-center justify-end gap-1.5 sm:gap-2">
                                        <!-- Papelera roja -->
                                        <button
                                            @click="openTrashModal(item, 'por-recibir')"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Retirar o descartar registro"
                                            aria-label="Retirar o descartar registro"
                                        >
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>

                                        <!-- Triángulo ámbar -->
                                        <button
                                            @click="openReceptionIncidentModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Registrar incidencia (faltantes, daños o diferencias)"
                                            aria-label="Registrar incidencia"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                                        </button>

                                        <!-- Palomita verde -->
                                        <button
                                            @click="handleAcceptSingleReception(item)"
                                            :disabled="submittingIndividual[item.order_item_id]"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 active:scale-95 transition cursor-pointer shadow-2xs disabled:opacity-50"
                                            title="Confirmar fijos recibidos"
                                            aria-label="Confirmar fijos recibidos"
                                        >
                                            <i class="fa-solid fa-check text-sm font-black"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. TABLA: POR LLENAR                                                      -->
            <!-- ========================================================================= -->
            <div v-if="currentTab === 'por-llenar'" class="space-y-3">
                <!-- Acción masiva compacta encima de la tabla -->
                <div class="flex items-center justify-between gap-3 bg-white px-3 sm:px-4 py-2.5 rounded-xl border border-slate-200 shadow-2xs">
                    <span class="text-xs font-bold text-slate-600">
                        {{ fixedToFill.length }} {{ fixedToFill.length === 1 ? 'cliente pendiente' : 'clientes pendientes' }}
                    </span>

                    <button
                        v-if="fixedToFill.length > 0"
                        @click="openFillAllModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white shadow-2xs transition active:scale-95 min-h-[44px] cursor-pointer"
                        title="Marcar todos como llenados"
                        aria-label="Marcar todos como llenados"
                    >
                        <i class="fa-solid fa-check-double text-sky-400"></i>
                        <span>Llenar todos</span>
                    </button>
                </div>

                <!-- Tabla compacta (Cliente, Fijos, Acciones) -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div v-if="fixedToFill.length === 0" class="p-12 text-center space-y-2">
                        <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                            <i class="fa-solid fa-bottle-water"></i>
                        </div>
                        <p class="text-sm font-bold text-slate-800">No hay fijos pendientes por llenar</p>
                        <p class="text-xs text-slate-400">Todos los botellones fijos recibidos aptos han sido lavados y llenados.</p>
                    </div>

                    <table v-else class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                <th scope="col" class="py-3 px-3 sm:px-5">Cliente</th>
                                <th scope="col" class="py-3 px-2 sm:px-4 text-right">Fijos</th>
                                <th scope="col" class="py-3 px-3 sm:px-5 text-right w-[156px] sm:w-[168px]">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in fixedToFill"
                                :key="item.order_item_id"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <!-- 1. Cliente: Apodo en negrita sin comillas, nombre debajo si existe -->
                                <td class="py-3.5 px-3 sm:px-5 align-middle">
                                    <span class="font-bold text-slate-900 text-sm sm:text-base leading-tight block">
                                        {{ item.customer_nickname || item.customer_name || 'Cliente' }}
                                    </span>
                                    <span
                                        v-if="item.customer_nickname && item.customer_name"
                                        class="text-xs text-slate-500 font-normal mt-0.5 block"
                                    >
                                        {{ item.customer_name }}
                                    </span>
                                </td>

                                <!-- 2. Fijos: únicamente el número -->
                                <td class="py-3.5 px-2 sm:px-4 align-middle text-right">
                                    <span class="font-bold sm:font-black text-slate-900 text-base sm:text-lg font-mono">
                                        {{ item.pending_quantity }}
                                    </span>
                                </td>

                                <!-- 3. Acciones: Papelera roja, Triángulo ámbar, Palomita verde -->
                                <td class="py-3.5 px-3 sm:px-5 align-middle text-right">
                                    <div class="flex items-center justify-end gap-1.5 sm:gap-2">
                                        <!-- Papelera roja -->
                                        <button
                                            @click="openTrashModal(item, 'por-llenar')"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Retirar o descartar registro"
                                            aria-label="Retirar o descartar registro"
                                        >
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>

                                        <!-- Triángulo ámbar -->
                                        <button
                                            @click="openFillIncidentModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Registrar incidencia por daño durante lavado o llenado"
                                            aria-label="Registrar incidencia"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                                        </button>

                                        <!-- Palomita verde -->
                                        <button
                                            @click="openFillModal(item)"
                                            type="button"
                                            class="h-11 w-11 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 active:scale-95 transition cursor-pointer shadow-2xs"
                                            title="Confirmar fijos llenados"
                                            aria-label="Confirmar fijos llenados"
                                        >
                                            <i class="fa-solid fa-check text-sm font-black"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: RETIRAR REGISTRO (PAPELERA ROJA)                                   -->
        <!-- ========================================================================= -->
        <div
            v-if="showTrashModal && itemForTrash"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-rose-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-rose-100 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg shrink-0">
                            <i class="fa-solid fa-trash-can"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block">Confirmación de retiro</span>
                            <h3 class="text-base font-black text-slate-900">
                                {{ itemForTrash.customer_nickname || itemForTrash.customer_name || 'Cliente' }}
                            </h3>
                            <p v-if="itemForTrash.customer_nickname && itemForTrash.customer_name" class="text-xs text-slate-500">
                                {{ itemForTrash.customer_name }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="closeTrashModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-3.5 bg-rose-50/70 rounded-2xl border border-rose-100 text-xs text-rose-950 space-y-2">
                    <p class="font-bold text-slate-900">
                        ¿Deseas retirar a este cliente de la lista de {{ trashTab === 'por-recibir' ? 'recepción física' : 'lavado y llenado' }}?
                    </p>
                    <p v-if="trashTab === 'por-recibir'" class="text-slate-600 leading-relaxed">
                        Este registro cuenta con <strong>{{ itemForTrash.expected_quantity }} botellones fijos</strong> reportados por el Repartidor. Para dar de baja unidades con diferencias o mermas y conservar la custodia, pagos y auditoría, debe derivarse a <strong>Incidencia</strong>.
                    </p>
                    <p v-else class="text-slate-600 leading-relaxed">
                        Este registro cuenta con <strong>{{ itemForTrash.pending_quantity }} botellones fijos</strong> en custodia de Planta. Si no se llenarán por fisura, rotura o contaminación, deben darse de baja mediante <strong>Incidencia</strong> para mantener la auditoría del inventario.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeTrashModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="handleTrashDeriveToIncident"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black min-h-[44px] flex items-center gap-2 cursor-pointer shadow-2xs"
                    >
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Registrar incidencia</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: INCIDENCIA EN RECEPCIÓN (POR RECIBIR)                              -->
        <!-- ========================================================================= -->
        <div
            v-if="showReceptionIncidentModal && itemForReceptionIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-amber-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-amber-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">Incidencia en recepción física</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemForReceptionIncident.customer_nickname || itemForReceptionIncident.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500">Esperados: {{ itemForReceptionIncident.expected_quantity }} botellones fijos</p>
                    </div>
                    <button
                        @click="closeReceptionIncidentModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-1">
                            <label class="font-bold text-emerald-900 block">Unidades aptas recibidas:</label>
                            <input
                                v-model.number="receptionIncidentForm.received_quantity"
                                type="number"
                                min="0"
                                :max="itemForReceptionIncident.expected_quantity"
                                class="w-full text-base font-black text-emerald-950 font-mono rounded-xl border-emerald-300"
                            />
                        </div>

                        <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 space-y-1">
                            <label class="font-bold text-amber-900 block">Unidades dañadas/rotas:</label>
                            <input
                                v-model.number="receptionIncidentForm.damaged_quantity"
                                type="number"
                                min="0"
                                :max="itemForReceptionIncident.expected_quantity"
                                class="w-full text-base font-black text-amber-950 font-mono rounded-xl border-amber-300"
                            />
                        </div>
                    </div>

                    <div v-if="receptionIncidentForm.damaged_quantity > 0" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                        <label class="font-bold text-slate-700 block">Tipo y detalle del daño:</label>
                        <select v-model="receptionIncidentForm.damage_type" class="w-full text-xs rounded-xl border-slate-300">
                            <option value="rotura">Rotura / Quebrado</option>
                            <option value="fisura">Fisura / Fuga</option>
                            <option value="contaminacion">Contaminación</option>
                            <option value="deformacion">Deformación</option>
                            <option value="otro">Otro</option>
                        </select>
                        <input
                            v-model="receptionIncidentForm.damage_notes"
                            type="text"
                            placeholder="Descripción del daño (obligatorio)..."
                            class="w-full text-xs rounded-xl border-slate-300"
                        />
                    </div>

                    <div v-if="receptionShortage > 0" class="p-3 bg-rose-50 rounded-2xl border border-rose-200 space-y-1">
                        <label class="font-bold text-rose-900 block">Faltan {{ receptionShortage }} botellón(es). Motivo obligatorio:</label>
                        <input
                            v-model="receptionIncidentForm.difference_reason"
                            type="text"
                            placeholder="Ej. Chofer reportó que cliente no entregó el 2do envase..."
                            class="w-full text-xs rounded-xl border-rose-300"
                        />
                    </div>

                    <p class="text-[11px] text-slate-500">
                        * Solo las unidades aptas recibidas pasarán a la etapa "Por llenar".
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeReceptionIncidentModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitReceptionIncident"
                        :disabled="receptionIncidentForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ receptionIncidentForm.processing ? 'Guardando...' : 'Guardar incidencia' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: ACEPTAR TODOS EN RECEPCIÓN                                         -->
        <!-- ========================================================================= -->
        <div
            v-if="showAcceptAllModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3 text-blue-900 border-b border-slate-100 pb-3">
                    <div class="h-10 w-10 rounded-2xl bg-blue-50 text-blue-900 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Aceptar todos los fijos recibidos</h3>
                        <p class="text-xs text-slate-500">Confirmación masiva de recepción física en Planta</p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Pedidos a confirmar:</span>
                        <span class="font-black text-slate-900 font-mono text-sm">{{ eligibleForAcceptAll.length }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Total de botellones aptos:</span>
                        <span class="font-black text-blue-900 font-mono text-base">{{ totalBottlesForAcceptAll }} unidades</span>
                    </div>

                    <div v-if="excludedFromAcceptAll.length > 0" class="pt-2 border-t border-slate-200 text-amber-800">
                        <p class="font-bold">Se excluirán {{ excludedFromAcceptAll.length }} pedidos con diferencias previas para revisarse individualmente.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeAcceptAllModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitAcceptAll"
                        :disabled="acceptAllForm.processing || eligibleForAcceptAll.length === 0"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ acceptAllForm.processing ? 'Confirmando...' : 'Confirmar recepción masiva' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: MARCAR COMO LLENADOS (POR LLENAR)                                  -->
        <!-- ========================================================================= -->
        <div
            v-if="showFillModal && itemToFill"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Confirmación de llenado</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToFill.customer_nickname || itemToFill.customer_name || 'Cliente' }}
                        </h3>
                        <p class="text-xs text-slate-500">Pendientes por llenar: {{ itemToFill.pending_quantity }} botellones</p>
                    </div>
                    <button
                        @click="closeFillModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-2">
                    <span class="text-xs font-bold text-slate-600 block">Cantidad lavada y llenada físicamente</span>
                    <div class="flex items-center justify-center gap-4">
                        <button
                            @click="decrementFillQty"
                            :disabled="fillForm.quantity <= 1"
                            type="button"
                            class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs cursor-pointer"
                            aria-label="Disminuir cantidad"
                        >
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <span class="text-3xl font-black text-slate-900 font-mono w-16 text-center">
                            {{ fillForm.quantity }}
                        </span>
                        <button
                            @click="incrementFillQty"
                            :disabled="fillForm.quantity >= itemToFill.pending_quantity"
                            type="button"
                            class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs cursor-pointer"
                            aria-label="Aumentar cantidad"
                        >
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <p v-if="fillForm.quantity < itemToFill.pending_quantity" class="text-[11px] text-amber-700 font-bold bg-amber-50 p-1.5 rounded-lg border border-amber-200">
                        Se confirmarán {{ fillForm.quantity }} botellones y el resto seguirá en "Por llenar".
                    </p>
                </div>

                <p class="text-[11px] text-slate-500">
                    * Al confirmar, las unidades terminadas aparecerán automáticamente en <strong>Reparto → Listos para cargar</strong>.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeFillModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitFill"
                        :disabled="fillForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ fillForm.processing ? 'Guardando...' : `Confirmar llenado (${fillForm.quantity})` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: INCIDENCIA EN LLENADO (POR LLENAR)                                 -->
        <!-- ========================================================================= -->
        <div
            v-if="showFillIncidentModal && itemForFillIncident"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-amber-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-amber-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">Incidencia en lavado o llenado</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemForFillIncident.customer_nickname || itemForFillIncident.customer_name || 'Cliente' }}
                        </h3>
                    </div>
                    <button
                        @click="closeFillIncidentModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer"
                        aria-label="Cerrar modal"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Cantidad de botellones dañados:</label>
                        <input
                            v-model.number="fillIncidentForm.quantity"
                            type="number"
                            min="1"
                            :max="itemForFillIncident.pending_quantity"
                            class="w-full text-base font-black font-mono rounded-xl border-slate-300"
                        />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Tipo de daño detectado:</label>
                        <select v-model="fillIncidentForm.damage_type" class="w-full text-xs rounded-xl border-slate-300">
                            <option value="rotura">Rotura en túnel / tina</option>
                            <option value="fisura">Fisura en boquilla / fondo</option>
                            <option value="contaminacion">Contaminación química o de olor</option>
                            <option value="deformacion">Deformación térmica</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Descripción del daño (obligatorio):</label>
                        <textarea
                            v-model="fillIncidentForm.notes"
                            rows="3"
                            placeholder="Ej. Se detectó fisura en la base durante el proceso de presurización..."
                            class="w-full text-xs rounded-xl border-slate-300"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeFillIncidentModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitFillIncident"
                        :disabled="fillIncidentForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ fillIncidentForm.processing ? 'Registrando...' : 'Registrar daño' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: MARCAR TODOS COMO LLENADOS                                         -->
        <!-- ========================================================================= -->
        <div
            v-if="showFillAllModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3 text-blue-900 border-b border-slate-100 pb-3">
                    <div class="h-10 w-10 rounded-2xl bg-blue-50 text-blue-900 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Llenar todos los botellones fijos</h3>
                        <p class="text-xs text-slate-500">Confirmación masiva de botellones fijos preparados</p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Pedidos a procesar:</span>
                        <span class="font-black text-slate-900 font-mono text-sm">{{ totalOrdersForFillAll }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Total de botellones fijos:</span>
                        <span class="font-black text-blue-900 font-mono text-base">{{ totalBottlesForFillAll }} unidades</span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    * Todos los fijos confirmados pasarán a estar disponibles de inmediato en <strong>Reparto → Listos para cargar</strong>.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeFillAllModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px] cursor-pointer"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitFillAll"
                        :disabled="fillAllForm.processing || totalOrdersForFillAll === 0"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px] cursor-pointer shadow-2xs"
                    >
                        {{ fillAllForm.processing ? 'Confirmando...' : 'Confirmar llenado masivo' }}
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
