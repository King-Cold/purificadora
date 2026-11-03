<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    driver: {
        type: Object,
        required: true,
    },
    truckInventory: {
        type: Object,
        required: true,
    },
    fixedByCustomer: {
        type: Array,
        default: () => [],
    },
    pendingTransfers: {
        type: Array,
        default: () => [],
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.transfer || page.props.errors?.unload || null);

// Modal para aceptar transferencia
const selectedTransfer = ref(null);
const showAcceptModal = ref(false);
const acceptForm = useForm({
    confirmed_quantity: 0,
    difference_reason: '',
});

const openAcceptModal = (transfer) => {
    selectedTransfer.value = transfer;
    acceptForm.confirmed_quantity = transfer.quantity;
    acceptForm.difference_reason = '';
    acceptForm.clearErrors();
    showAcceptModal.value = true;
};

const closeAcceptModal = () => {
    showAcceptModal.value = false;
    selectedTransfer.value = null;
};

const submitAcceptTransfer = () => {
    if (!selectedTransfer.value) return;

    acceptForm.post(route('repartidor.truck-load.accept', selectedTransfer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeAcceptModal();
        },
    });
};

// Modal para rechazar transferencia
const showRejectModal = ref(false);
const rejectForm = useForm({
    reason: '',
});

const openRejectModal = (transfer) => {
    selectedTransfer.value = transfer;
    rejectForm.reason = '';
    rejectForm.clearErrors();
    showRejectModal.value = true;
};

const closeRejectModal = () => {
    showRejectModal.value = false;
    selectedTransfer.value = null;
};

const submitRejectTransfer = () => {
    if (!selectedTransfer.value) return;

    rejectForm.post(route('repartidor.truck-load.reject', selectedTransfer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeRejectModal();
        },
    });
};

// Modal para proponer descarga a planta
const showUnloadModal = ref(false);
const unloadForm = useForm({
    bottle_type: 'cambio',
    bottle_condition: 'empty',
    quantity: 1,
    customer_id: null,
    notes: '',
});

const openUnloadModal = () => {
    unloadForm.bottle_type = 'cambio';
    unloadForm.bottle_condition = 'empty';
    unloadForm.quantity = Math.max(1, props.truckInventory.exchange_empty_collected || 1);
    unloadForm.customer_id = null;
    unloadForm.notes = '';
    unloadForm.clearErrors();
    showUnloadModal.value = true;
};

const closeUnloadModal = () => {
    showUnloadModal.value = false;
};

const submitUnload = () => {
    unloadForm.post(route('repartidor.truck-load.propose-unload'), {
        preserveScroll: true,
        onSuccess: () => {
            closeUnloadModal();
        },
    });
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    return d.toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getMovementBadgeClass = (type) => {
    switch (type) {
        case 'truck_load_exchange':
        case 'truck_load_fixed':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'delivery_refill_to_customer':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'collection_exchange_empty':
        case 'collection_fixed_from_customer':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'truck_unload_exchange':
        case 'plant_reception_fixed':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'return_empty_fixed_to_customer':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const getMovementLabel = (type) => {
    const labels = {
        'truck_load_exchange': 'Carga de Cambio Lleno',
        'truck_load_fixed': 'Carga de Fijo Lleno',
        'delivery_refill_to_customer': 'Entrega en Domicilio',
        'collection_exchange_empty': 'Recolección Vacío Cambio',
        'collection_fixed_from_customer': 'Recolección Fijo Vacío',
        'truck_unload_exchange': 'Descarga Vacíos a Planta',
        'plant_reception_fixed': 'Entrega de Fijos a Planta',
        'return_empty_fixed_to_customer': 'Devolución de Vacíos (Incidencia)',
    };
    return labels[type] || type;
};
</script>

<template>
    <Head title="Mi Carga - Existencias del Camión" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-truck text-blue-900"></i> Mi Carga y Existencias de Camión
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Chofer asignado: <span class="font-semibold text-slate-700">{{ driver.name }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canManage"
                        @click="openUnloadModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 shadow-2xs active:scale-95 transition"
                    >
                        <i class="fa-solid fa-arrow-down"></i> Descargar a Planta
                    </button>
                    <Link
                        :href="route('repartidor.dashboard')"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                    >
                        Volver
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Mensajes Flash de Éxito / Error -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-circle-check text-lg text-emerald-600 mt-0.5"></i>
                <div class="flex-1 font-medium">{{ flashSuccess }}</div>
            </div>

            <div
                v-if="flashError"
                class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-triangle-exclamation text-lg text-rose-600 mt-0.5"></i>
                <div class="flex-1 font-medium">{{ flashError }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. RESUMEN DE EXISTENCIAS CONTINUAS DEL CAMIÓN                            -->
            <!-- ========================================================================= -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- 1. Cambios Llenos Disponibles -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-blue-100 shadow-2xs relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Cambios Llenos</span>
                        <i class="fa-solid fa-bottle-water text-blue-600 text-xl"></i>
                    </div>
                    <div class="text-3xl sm:text-4xl font-extrabold text-blue-900">
                        {{ truckInventory.exchange_full_available }}
                    </div>
                    <p class="text-[11px] sm:text-xs text-blue-700 mt-1 font-medium">
                        Disponibles para entrega en ruta
                    </p>
                </div>

                <!-- 2. Cambios Vacíos Recogidos -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-amber-100 shadow-2xs relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Cambios Vacíos</span>
                        <i class="fa-solid fa-arrows-rotate text-amber-600 text-xl"></i>
                    </div>
                    <div class="text-3xl sm:text-4xl font-extrabold text-amber-900">
                        {{ truckInventory.exchange_empty_collected }}
                    </div>
                    <p class="text-[11px] sm:text-xs text-amber-700 mt-1 font-medium">
                        Recogidos listos para planta
                    </p>
                </div>

                <!-- 3. Fijos Vacíos en Custodia -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-indigo-100 shadow-2xs relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Fijos Vacíos</span>
                        <i class="fa-solid fa-boxes-stacked text-indigo-600 text-xl"></i>
                    </div>
                    <div class="text-3xl sm:text-4xl font-extrabold text-indigo-900">
                        {{ truckInventory.fixed_empty_collected_total }}
                    </div>
                    <p class="text-[11px] sm:text-xs text-indigo-700 mt-1 font-medium">
                        Llevando a planta (por cliente)
                    </p>
                </div>

                <!-- 4. Fijos Llenos para Entregar -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-emerald-100 shadow-2xs relative overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Fijos Llenos</span>
                        <i class="fa-solid fa-circle-check text-emerald-600 text-xl"></i>
                    </div>
                    <div class="text-3xl sm:text-4xl font-extrabold text-emerald-900">
                        {{ truckInventory.fixed_full_to_deliver_total }}
                    </div>
                    <p class="text-[11px] sm:text-xs text-emerald-700 mt-1 font-medium">
                        Preparados para devolver al cliente
                    </p>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. TRANSFERENCIAS PENDIENTES DE ACEPTAR DESDE PLANTA                       -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                            <i class="fa-solid fa-inbox text-blue-900"></i> Transferencias Pendientes de Planta
                            <span
                                v-if="pendingTransfers.length > 0"
                                class="px-2 py-0.5 rounded-full text-xs font-extrabold bg-blue-100 text-blue-700"
                            >
                                {{ pendingTransfers.length }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Cargas propuestas por planta. Deben confirmarse físicamente antes de sumarse a tu carga.
                        </p>
                    </div>
                </div>

                <div v-if="pendingTransfers.length === 0" class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center text-xl mb-2">
                        <i class="fa-regular fa-clipboard text-slate-400 text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-700">No hay transferencias pendientes de planta</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        Cuando el Operador de Planta autorice y prepare una carga, aparecerá aquí para tu confirmación física (Módulo de Planta - Sprint 4).
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="transfer in pendingTransfers"
                        :key="transfer.id"
                        class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ transfer.bottle_type === 'cambio' ? 'Cambio Lleno' : 'Fijo de Cliente' }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    Fecha: {{ formatDateTime(transfer.created_at) }}
                                </span>
                                <span v-if="transfer.user" class="text-xs text-slate-500">
                                    Autorizó: {{ transfer.user.name }}
                                </span>
                            </div>

                            <div class="flex items-baseline gap-2">
                                <span class="text-2xl font-extrabold text-slate-800">
                                    {{ transfer.quantity }} u.
                                </span>
                                <span class="text-xs font-medium text-slate-500">
                                    propuestas para cargar al camión
                                </span>
                            </div>

                            <!-- Si es fijo, mostrar cliente -->
                            <div v-if="transfer.customer" class="text-xs text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100 mt-1 inline-block">
                                <span class="font-bold text-slate-800">Propietario:</span>
                                {{ transfer.customer.nickname ? `"${transfer.customer.nickname}" - ` : '' }}
                                {{ transfer.customer.name }} ({{ transfer.customer.code }})
                            </div>

                            <p v-if="transfer.notes" class="text-xs text-slate-500 italic">
                                Nota: {{ transfer.notes }}
                            </p>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-2 shrink-0">
                            <button
                                @click="openAcceptModal(transfer)"
                                type="button"
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs inline-flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-check"></i> Confirmar y Aceptar
                            </button>
                            <button
                                @click="openRejectModal(transfer)"
                                type="button"
                                class="w-full sm:w-auto px-3 py-2.5 rounded-xl text-xs sm:text-sm font-medium bg-slate-100 text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition"
                            >
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. DESGLOSE DE BOTELLONES FIJOS POR CLIENTE PROPIETARIO                   -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                        <i class="fa-solid fa-tag text-purple-900"></i> Custodia de Envases Fijos por Cliente
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Los fijos se transportan y devuelven estrictamente a su propietario identificado.
                    </p>
                </div>

                <div v-if="fixedByCustomer.length === 0" class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center text-xl mb-2">
                        <i class="fa-solid fa-box text-slate-400 text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-700">No hay envases fijos en el camión</p>
                    <p class="text-xs text-slate-400 mt-1">
                        Aparecerán aquí cuando recojas envases en ruta o cargues garrafones preparados en planta.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="item in fixedByCustomer"
                        :key="item.customer_id"
                        class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 transition"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-slate-900 text-base">
                                    {{ item.customer_nickname ? `"${item.customer_nickname}"` : item.customer_name }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-600">
                                    {{ item.customer_code }}
                                </span>
                            </div>
                            <p v-if="item.customer_nickname && item.customer_name" class="text-xs text-slate-500">
                                Nombre registrado: {{ item.customer_name }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <div
                                v-if="item.fixed_empty_collected > 0"
                                class="px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-800 text-xs font-semibold flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-arrow-down text-indigo-600"></i>
                                <span>Vacíos:</span>
                                <span class="text-sm font-extrabold">{{ item.fixed_empty_collected }} u.</span>
                            </div>

                            <div
                                v-if="item.fixed_full_to_deliver > 0"
                                class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-xs font-semibold flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-check text-emerald-600"></i>
                                <span>Llenos:</span>
                                <span class="text-sm font-extrabold">{{ item.fixed_full_to_deliver }} u.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. HISTORIAL RECIENTE DE MOVIMIENTOS                                      -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-700"></i> Historial Reciente de Movimientos
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Registro auditable de cargas, entregas, recolecciones y descargas.
                    </p>
                </div>

                <div v-if="recentMovements.length === 0" class="p-6 text-center text-sm text-slate-400">
                    No se registran movimientos recientes de custodia.
                </div>

                <div v-else class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                    <div
                        v-for="mov in recentMovements"
                        :key="mov.id"
                        class="p-3.5 sm:p-4 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2 hover:bg-slate-50 transition"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    class="px-2 py-0.5 rounded-md font-bold uppercase tracking-wider text-[10px] border"
                                    :class="getMovementBadgeClass(mov.movement_type)"
                                >
                                    {{ getMovementLabel(mov.movement_type) }}
                                </span>
                                <span class="font-bold text-slate-800 text-sm">
                                    {{ mov.quantity }} u.
                                </span>
                                <span class="text-slate-400">
                                    ({{ mov.bottle_type }} {{ mov.bottle_condition === 'full' ? 'lleno' : 'vacío' }})
                                </span>
                                <span
                                    v-if="mov.status !== 'accepted'"
                                    class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800"
                                >
                                    {{ mov.status }}
                                </span>
                            </div>

                            <p class="text-slate-600">
                                <span class="font-medium text-slate-500">Ruta:</span> {{ mov.origin }} <i class="fa-solid fa-arrow-right text-[10px] mx-1 text-slate-400"></i> {{ mov.destination }}
                                <template v-if="mov.customer">
                                    • <span class="font-medium text-slate-500">Cliente:</span> {{ mov.customer.nickname || mov.customer.name }} ({{ mov.customer.code }})
                                </template>
                            </p>

                            <p v-if="mov.difference_reason" class="text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-100 flex items-center gap-1.5">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i> Motivo de diferencia: {{ mov.difference_reason }}
                            </p>
                        </div>

                        <div class="text-right text-slate-400 shrink-0">
                            <div>{{ formatDateTime(mov.created_at) }}</div>
                            <div v-if="mov.confirmed_by_user" class="text-[11px] text-slate-500">
                                Conf: {{ mov.confirmed_by_user.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: ACEPTAR TRANSFERENCIA                                              -->
        <!-- ========================================================================= -->
        <div
            v-if="showAcceptModal && selectedTransfer"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-check text-emerald-600"></i> Confirmar Recepción Física
                    </h3>
                    <button @click="closeAcceptModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="text-xs text-slate-600 bg-blue-50 p-3 rounded-xl border border-blue-100 space-y-1">
                    <p><span class="font-bold text-blue-900">Tipo:</span> {{ selectedTransfer.bottle_type === 'cambio' ? 'Botellones de Cambio Llenos' : 'Botellones Fijos de Cliente' }}</p>
                    <p><span class="font-bold text-blue-900">Cantidad propuesta por planta:</span> {{ selectedTransfer.proposed_quantity || selectedTransfer.quantity }} unidades</p>
                </div>

                <form @submit.prevent="submitAcceptTransfer" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Cantidad real recibida en el camión *
                        </label>
                        <input
                            v-model.number="acceptForm.confirmed_quantity"
                            type="number"
                            min="0"
                            class="w-full text-base font-bold text-slate-900 rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="acceptForm.errors.confirmed_quantity" class="text-xs text-rose-600 mt-1">
                            {{ acceptForm.errors.confirmed_quantity }}
                        </p>
                    </div>

                    <!-- Si la cantidad confirmada difiere de la propuesta, exigir motivo -->
                    <div v-if="acceptForm.confirmed_quantity !== (selectedTransfer.proposed_quantity || selectedTransfer.quantity)">
                        <label class="block text-xs font-bold text-amber-800 mb-1">
                            Motivo de la diferencia * (Obligatorio)
                        </label>
                        <textarea
                            v-model="acceptForm.difference_reason"
                            rows="2"
                            class="w-full text-xs rounded-xl border-amber-300 focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Ej. Faltaron 2 garrafones por envase golpeado o merma en planta"
                            required
                        ></textarea>
                        <p v-if="acceptForm.errors.difference_reason" class="text-xs text-rose-600 mt-1">
                            {{ acceptForm.errors.difference_reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeAcceptModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="acceptForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ acceptForm.processing ? 'Confirmando...' : 'Aceptar y Sumar a Carga' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: RECHAZAR TRANSFERENCIA                                             -->
        <!-- ========================================================================= -->
        <div
            v-if="showRejectModal && selectedTransfer"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-rose-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Rechazar Transferencia
                    </h3>
                    <button @click="closeRejectModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitRejectTransfer" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Motivo de rechazo * (Obligatorio)
                        </label>
                        <textarea
                            v-model="rejectForm.reason"
                            rows="3"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                            placeholder="Ej. Carga no autorizada, no corresponde al turno o capacidad excedida"
                            required
                        ></textarea>
                        <p v-if="rejectForm.errors.reason" class="text-xs text-rose-600 mt-1">
                            {{ rejectForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeRejectModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="rejectForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ rejectForm.processing ? 'Rechazando...' : 'Confirmar Rechazo' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: REGISTRAR DESCARGA A PLANTA                                        -->
        <!-- ========================================================================= -->
        <div
            v-if="showUnloadModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-arrow-down text-slate-700"></i> Registrar Descarga a Planta
                    </h3>
                    <button @click="closeUnloadModal" type="button" class="text-slate-400 hover:text-slate-700">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form @submit.prevent="submitUnload" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Modalidad de envase *</label>
                        <select
                            v-model="unloadForm.bottle_type"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        >
                            <option value="cambio">Cambio</option>
                            <option value="fijo">Fijo de Cliente Propietario</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Condición *</label>
                        <select
                            v-model="unloadForm.bottle_condition"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        >
                            <option value="empty">Vacío (recogido en ruta)</option>
                            <option value="full">Lleno (sobrante no entregado)</option>
                        </select>
                    </div>

                    <!-- Si es fijo, seleccionar cliente propietario disponible -->
                    <div v-if="unloadForm.bottle_type === 'fijo'">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Cliente Propietario *</label>
                        <select
                            v-model="unloadForm.customer_id"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        >
                            <option :value="null" disabled>Seleccionar cliente con fijos en camión</option>
                            <option
                                v-for="c in fixedByCustomer"
                                :key="c.customer_id"
                                :value="c.customer_id"
                            >
                                {{ c.customer_nickname ? `"${c.customer_nickname}" - ` : '' }}{{ c.customer_name }} ({{ c.customer_code }}) - Vacíos: {{ c.fixed_empty_collected }}
                            </option>
                        </select>
                        <p v-if="unloadForm.errors.customer_id" class="text-xs text-rose-600 mt-1">
                            {{ unloadForm.errors.customer_id }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Cantidad a descargar *</label>
                        <input
                            v-model.number="unloadForm.quantity"
                            type="number"
                            min="1"
                            class="w-full text-base font-bold text-slate-900 rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="unloadForm.errors.quantity" class="text-xs text-rose-600 mt-1">
                            {{ unloadForm.errors.quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Notas u observaciones</label>
                        <input
                            v-model="unloadForm.notes"
                            type="text"
                            placeholder="Ej. Retorno al cierre de ruta"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="closeUnloadModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-600 hover:bg-slate-100"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="unloadForm.processing"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition"
                        >
                            {{ unloadForm.processing ? 'Registrando...' : 'Registrar Descarga' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

