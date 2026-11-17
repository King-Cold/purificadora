<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
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
    truckSummary: {
        type: Object,
        default: () => ({
            driver_id: null,
            driver_name: 'Repartidor',
            exchange_full_available: 0,
            exchange_empty_collected: 0,
        }),
    },
    supervisionSummary: {
        type: Object,
        default: () => null,
    },
    initialCountPending: {
        type: Boolean,
        default: false,
    },
    initialCountInfo: {
        type: Object,
        default: null,
    },
    recentExchangeMovements: {
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
const formError = computed(() => {
    const errors = page.props.errors || {};
    return errors.quantity || errors.driver || errors.initial_count || errors.empty_count || errors.full_count || null;
});

// Totales del circuito (Planta + Camión = Total esperado)
const plantTotal = computed(() => Number(props.balances.exchange_empty_available || 0) + Number(props.balances.exchange_full_ready || 0));
const truckTotal = computed(() => Number(props.truckSummary.exchange_full_available || 0) + Number(props.truckSummary.exchange_empty_collected || 0));
const activeTotal = computed(() => plantTotal.value + truckTotal.value + Number(props.balances.exchange_washing_filling || 0) + Number(props.balances.exchange_full_reserved || 0));
const targetQuantity = computed(() => Number(props.supervisionSummary?.target_quantity ?? activeTotal.value));
const isTargetExact = computed(() => activeTotal.value === targetQuantity.value);

// Helper para ajustar cantidades dentro de límites seguros
const clampQty = (val, max) => {
    const num = parseInt(val, 10) || 1;
    const upper = max > 0 ? max : 1;
    return Math.max(1, Math.min(upper, num));
};

// =========================================================================
// ACCIÓN 1: RECIBIR VACÍOS DEL CAMIÓN (1 CLIC)
// =========================================================================
const receiveForm = useForm({
    quantity: Math.max(1, props.truckSummary.exchange_empty_collected || 1),
    driver_id: props.truckSummary.driver_id,
});

watch(() => props.truckSummary.exchange_empty_collected, (val) => {
    receiveForm.quantity = val > 0 ? val : 1;
    receiveForm.driver_id = props.truckSummary.driver_id;
});

const submitReceiveEmpties = () => {
    if (receiveForm.processing || props.truckSummary.exchange_empty_collected <= 0) return;
    receiveForm.quantity = clampQty(receiveForm.quantity, props.truckSummary.exchange_empty_collected);
    receiveForm.driver_id = props.truckSummary.driver_id;
    receiveForm.post(route('planta.cambios.receive-empties'), {
        preserveScroll: true,
    });
};

// =========================================================================
// ACCIÓN 2: LLENAR CAMBIOS EN PLANTA (1 CLIC)
// =========================================================================
const fillForm = useForm({
    quantity: Math.max(1, Math.min(10, props.balances.exchange_empty_available || 1)),
});

watch(() => props.balances.exchange_empty_available, (val) => {
    fillForm.quantity = val > 0 ? Math.min(fillForm.quantity || val, val) : 1;
});

const submitFill = () => {
    if (fillForm.processing || props.balances.exchange_empty_available <= 0) return;
    fillForm.quantity = clampQty(fillForm.quantity, props.balances.exchange_empty_available);
    fillForm.post(route('planta.cambios.fill'), {
        preserveScroll: true,
    });
};

// =========================================================================
// ACCIÓN 3: SUBIR LLENOS AL CAMIÓN (1 CLIC)
// =========================================================================
const loadForm = useForm({
    quantity: Math.max(1, Math.min(10, props.balances.exchange_full_ready || 1)),
    driver_id: props.truckSummary.driver_id,
});

watch(() => props.balances.exchange_full_ready, (val) => {
    loadForm.quantity = val > 0 ? Math.min(loadForm.quantity || val, val) : 1;
    loadForm.driver_id = props.truckSummary.driver_id;
});

const submitLoadTruck = () => {
    if (loadForm.processing || props.balances.exchange_full_ready <= 0) return;
    loadForm.quantity = clampQty(loadForm.quantity, props.balances.exchange_full_ready);
    loadForm.driver_id = props.truckSummary.driver_id;
    loadForm.post(route('planta.cambios.load-truck'), {
        preserveScroll: true,
    });
};

// =========================================================================
// CONTEO INICIAL (SOLO SI ESTÁ PENDIENTE)
// =========================================================================
const initialCountForm = useForm({
    empty_count: 0,
    full_count: 0,
    notes: '',
});

const submitInitialCount = () => {
    initialCountForm.post(route('planta.inventory.initial-count'), {
        preserveScroll: true,
    });
};

// =========================================================================
// REPORTAR DAÑADO O SIN LOCALIZAR
// =========================================================================
const showInconvenienceModal = ref(false);
const inconvenienceForm = useForm({
    quantity: 1,
    origin_stage: 'plant_empty',
    reason: 'rotura',
    location_status: 'separado',
    notes: '',
});

const openInconvenienceModal = () => {
    inconvenienceForm.quantity = 1;
    inconvenienceForm.origin_stage = props.balances.exchange_empty_available > 0 ? 'plant_empty' : 'plant_full';
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

const getMovementMeta = (type) => {
    switch (type) {
        case 'truck_unload_exchange':
            return { label: 'Vacíos recibidos', badge: 'bg-[#E9F3F8] text-[#0071BA]', icon: 'fa-arrow-down' };
        case 'plant_washing_finished':
            return { label: 'Llenado en planta', badge: 'bg-[#E8F2EC] text-[#34785A]', icon: 'fa-droplet' };
        case 'truck_load_exchange':
            return { label: 'Subida a camión', badge: 'bg-[#DAD6E8]/75 text-[#1E1545]', icon: 'fa-truck-ramp-box' };
        case 'plant_washing_started':
            return { label: 'Inicio de lavado', badge: 'bg-[#F4F3F8] text-[#747181]', icon: 'fa-soap' };
        default:
            return { label: 'Movimiento', badge: 'bg-[#F4F3F8] text-[#747181]', icon: 'fa-arrows-rotate' };
    }
};
</script>

<template>
    <Head title="Planta · Cambios 20L" />

    <AuthenticatedLayout>
        <div class="py-5 sm:py-7 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <div
                v-if="formError"
                class="px-4 py-3.5 rounded-2xl bg-[#FCECEF] border border-[#E82147]/25 text-[#292638] flex items-center gap-3"
                role="alert"
            >
                <div class="h-9 w-9 rounded-xl bg-[#E82147] text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <p class="text-sm font-semibold text-[#E82147] flex-1">{{ formError }}</p>
            </div>

            <!-- Encabezado compacto con acción discreta de incidencia -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg sm:text-xl font-bold text-[#292638] tracking-[-0.015em]">
                        Control de botellones de cambio
                    </h1>
                    <p class="text-xs text-[#747181] mt-0.5">
                        Flujo directo entre Planta y el camión de {{ truckSummary.driver_name }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="openInconvenienceModal"
                    class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl bg-[#F8F0DF] hover:bg-[#F3E5C8] text-[#A97827] border border-[#A97827]/20 text-xs font-semibold transition-colors cursor-pointer self-start sm:self-auto min-h-[40px]"
                >
                    <i class="fa-solid fa-triangle-exclamation text-xs"></i>
                    <span>Reportar dañado / perdido</span>
                </button>
            </div>

            <!-- Conteo inicial (visible únicamente si nunca se ha registrado) -->
            <div v-if="initialCountPending" class="bg-white rounded-2xl border border-[#A97827]/30 p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-xl bg-[#F8F0DF] text-[#A97827] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-[#292638]">Conteo inicial físico pendiente</h2>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Registra cuántos botellones de cambio hay actualmente en Planta para fijar la base del inventario.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitInitialCount" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-[#747181] mb-1">Vacíos en planta</label>
                        <input
                            v-model.number="initialCountForm.empty_count"
                            type="number"
                            min="0"
                            required
                            class="w-full rounded-xl border-[#DAD6E8] text-sm font-semibold text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[#747181] mb-1">Llenos en planta</label>
                        <input
                            v-model.number="initialCountForm.full_count"
                            type="number"
                            min="0"
                            required
                            class="w-full rounded-xl border-[#DAD6E8] text-sm font-semibold text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="initialCountForm.processing"
                        class="min-h-[42px] px-4 rounded-xl bg-[#1E1545] hover:bg-[#2B205E] text-white text-xs font-semibold transition-colors cursor-pointer"
                    >
                        Guardar conteo inicial
                    </button>
                </form>
            </div>

            <!-- ===================================================================== -->
            <!-- 1. TARJETAS DE CONTEO (EN PLANTA + EN CAMIÓN = TOTAL ESPERADO)        -->
            <!-- ===================================================================== -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <!-- En Planta -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0071BA] shrink-0"></span>
                            <span class="text-xs font-medium text-[#747181]">En Planta</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                {{ plantTotal }}
                            </span>
                            <span class="text-xs font-medium text-[#747181] tabular-nums truncate">
                                {{ balances.exchange_empty_available }} vacíos · {{ balances.exchange_full_ready }} llenos
                            </span>
                        </div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-industry text-base"></i>
                    </div>
                </div>

                <!-- En Camión -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#1E1545] shrink-0"></span>
                            <span class="text-xs font-medium text-[#747181]">En Camión</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                {{ truckTotal }}
                            </span>
                            <span class="text-xs font-medium text-[#747181] tabular-nums truncate">
                                {{ truckSummary.exchange_full_available }} llenos · {{ truckSummary.exchange_empty_collected }} vacíos
                            </span>
                        </div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-[#DAD6E8]/70 text-[#1E1545] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-truck text-base"></i>
                    </div>
                </div>

                <!-- Total Esperado (Planta + Camión) -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2 w-2 rounded-full shrink-0"
                                :class="isTargetExact ? 'bg-[#34785A]' : 'bg-[#A97827]'"
                            ></span>
                            <span class="text-xs font-medium text-[#747181]">Total Cambios (Planta + Camión)</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                {{ activeTotal }}
                            </span>
                            <span
                                class="text-xs font-semibold tabular-nums"
                                :class="isTargetExact ? 'text-[#34785A]' : 'text-[#A97827]'"
                            >
                                Meta: {{ targetQuantity }}
                            </span>
                        </div>
                    </div>
                    <div
                        class="h-11 w-11 rounded-xl flex items-center justify-center shrink-0"
                        :class="isTargetExact ? 'bg-[#E8F2EC] text-[#34785A]' : 'bg-[#F8F0DF] text-[#A97827]'"
                    >
                        <i class="fa-solid fa-scale-balanced text-base"></i>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 2. TRES ACCIONES DIRECTAS EN 1 CLIC (ADAPTADAS PARA MÓVIL Y DESKTOP)  -->
            <!-- ===================================================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-stretch">

                <!-- ACCIÓN 1: RECIBIR VACÍOS DEL CAMIÓN -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] flex flex-col overflow-hidden">
                    <div class="px-5 py-4 border-b border-[#E9E7F0] flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-9 w-9 rounded-xl bg-[#E9F3F8] text-[#0071BA] font-bold text-xs flex items-center justify-center shrink-0">
                                1
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Recibir vacíos</h2>
                                <p class="text-xs text-[#747181] truncate">Camión → Planta</p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-lg bg-[#E9F3F8] text-[#0071BA] text-xs font-semibold tabular-nums shrink-0">
                            {{ truckSummary.exchange_empty_collected }} en camión
                        </span>
                    </div>

                    <form @submit.prevent="submitReceiveEmpties" class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div class="rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] p-4 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <label for="receive-qty" class="text-xs font-medium text-[#747181]">
                                    Cantidad a recibir
                                </label>
                                <button
                                    v-if="truckSummary.exchange_empty_collected > 0"
                                    type="button"
                                    @click="receiveForm.quantity = truckSummary.exchange_empty_collected"
                                    class="text-xs font-semibold text-[#0071BA] hover:underline cursor-pointer"
                                >
                                    Todos ({{ truckSummary.exchange_empty_collected }})
                                </button>
                            </div>

                            <div class="flex items-center justify-center gap-2.5">
                                <button
                                    type="button"
                                    @click="receiveForm.quantity = Math.max(1, (Number(receiveForm.quantity) || 1) - 1)"
                                    :disabled="receiveForm.quantity <= 1 || truckSummary.exchange_empty_collected <= 0 || receiveForm.processing"
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#E9F3F8] text-[#292638] hover:text-[#0071BA] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar vacío"
                                >
                                    <i class="fa-solid fa-minus"></i>
                                </button>

                                <div class="flex-1 max-w-[130px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#0071BA] px-2.5 py-2">
                                    <input
                                        id="receive-qty"
                                        v-model.number="receiveForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, truckSummary.exchange_empty_collected)"
                                        :disabled="truckSummary.exchange_empty_collected <= 0"
                                        class="w-full text-center text-2xl font-bold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#A6A3B2]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="receiveForm.quantity = Math.min(Math.max(1, truckSummary.exchange_empty_collected), (Number(receiveForm.quantity) || 1) + 1)"
                                    :disabled="receiveForm.quantity >= truckSummary.exchange_empty_collected || truckSummary.exchange_empty_collected <= 0 || receiveForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#DCECF7] hover:bg-[#0071BA] text-[#0071BA] hover:text-white border border-[#0071BA]/25 active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar vacío"
                                >
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="receiveForm.processing || truckSummary.exchange_empty_collected <= 0"
                            class="w-full min-h-[48px] px-4 rounded-xl bg-[#0071BA] hover:bg-[#005D99] text-white font-semibold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:cursor-not-allowed"
                        >
                            <i v-if="receiveForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <i v-else class="fa-solid fa-arrow-down text-xs"></i>
                            <span class="tabular-nums">
                                {{ truckSummary.exchange_empty_collected > 0 ? `Recibir ${receiveForm.quantity} vacío(s)` : 'Sin vacíos en camión' }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- ACCIÓN 2: LLENAR CAMBIOS EN PLANTA -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] flex flex-col overflow-hidden">
                    <div class="px-5 py-4 border-b border-[#E9E7F0] flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-9 w-9 rounded-xl bg-[#E8F2EC] text-[#34785A] font-bold text-xs flex items-center justify-center shrink-0">
                                2
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Llenar cambios</h2>
                                <p class="text-xs text-[#747181] truncate">Vacíos → Llenos en Planta</p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-lg bg-[#E8F2EC] text-[#34785A] text-xs font-semibold tabular-nums shrink-0">
                            {{ balances.exchange_empty_available }} vacíos
                        </span>
                    </div>

                    <form @submit.prevent="submitFill" class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div class="rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] p-4 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <label for="fill-qty" class="text-xs font-medium text-[#747181]">
                                    Cantidad llenada
                                </label>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-for="preset in [5, 10]"
                                        :key="preset"
                                        v-show="balances.exchange_empty_available >= preset"
                                        type="button"
                                        @click="fillForm.quantity = preset"
                                        class="text-xs font-semibold px-2 py-0.5 rounded-md border transition-colors cursor-pointer"
                                        :class="fillForm.quantity === preset ? 'bg-[#E82147] text-white border-[#E82147]' : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                    >
                                        {{ preset }}
                                    </button>
                                    <button
                                        v-if="balances.exchange_empty_available > 0"
                                        type="button"
                                        @click="fillForm.quantity = balances.exchange_empty_available"
                                        class="text-xs font-semibold text-[#34785A] hover:underline cursor-pointer"
                                    >
                                        Todos
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-2.5">
                                <button
                                    type="button"
                                    @click="fillForm.quantity = Math.max(1, (Number(fillForm.quantity) || 1) - 1)"
                                    :disabled="fillForm.quantity <= 1 || balances.exchange_empty_available <= 0 || fillForm.processing"
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#E8F2EC] text-[#292638] hover:text-[#34785A] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar llenado"
                                >
                                    <i class="fa-solid fa-minus"></i>
                                </button>

                                <div class="flex-1 max-w-[130px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#34785A] px-2.5 py-2">
                                    <input
                                        id="fill-qty"
                                        v-model.number="fillForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, balances.exchange_empty_available)"
                                        :disabled="balances.exchange_empty_available <= 0"
                                        class="w-full text-center text-2xl font-bold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#A6A3B2]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="fillForm.quantity = Math.min(Math.max(1, balances.exchange_empty_available), (Number(fillForm.quantity) || 1) + 1)"
                                    :disabled="fillForm.quantity >= balances.exchange_empty_available || balances.exchange_empty_available <= 0 || fillForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#E8F2EC] hover:bg-[#34785A] text-[#34785A] hover:text-white border border-[#34785A]/20 active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar llenado"
                                >
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="fillForm.processing || balances.exchange_empty_available <= 0"
                            class="w-full min-h-[48px] px-4 rounded-xl bg-[#34785A] hover:bg-[#2A634A] text-white font-semibold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:cursor-not-allowed"
                        >
                            <i v-if="fillForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <i v-else class="fa-solid fa-check text-xs"></i>
                            <span class="tabular-nums">
                                {{ balances.exchange_empty_available > 0 ? `Marcar ${fillForm.quantity} llenado(s)` : 'Sin vacíos por llenar' }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- ACCIÓN 3: SUBIR LLENOS AL CAMIÓN -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] flex flex-col overflow-hidden">
                    <div class="px-5 py-4 border-b border-[#E9E7F0] flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-9 w-9 rounded-xl bg-[#DAD6E8]/75 text-[#1E1545] font-bold text-xs flex items-center justify-center shrink-0">
                                3
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Subir al camión</h2>
                                <p class="text-xs text-[#747181] truncate">Planta → {{ truckSummary.driver_name }}</p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-lg bg-[#DAD6E8]/60 text-[#1E1545] text-xs font-semibold tabular-nums shrink-0">
                            {{ balances.exchange_full_ready }} llenos
                        </span>
                    </div>

                    <form @submit.prevent="submitLoadTruck" class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div class="rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] p-4 space-y-3.5">
                            <div class="flex items-center justify-between">
                                <label for="load-qty" class="text-xs font-medium text-[#747181]">
                                    Cantidad para camión
                                </label>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-for="preset in [5, 10]"
                                        :key="preset"
                                        v-show="balances.exchange_full_ready >= preset"
                                        type="button"
                                        @click="loadForm.quantity = preset"
                                        class="text-xs font-semibold px-2 py-0.5 rounded-md border transition-colors cursor-pointer"
                                        :class="loadForm.quantity === preset ? 'bg-[#E82147] text-white border-[#E82147]' : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                    >
                                        {{ preset }}
                                    </button>
                                    <button
                                        v-if="balances.exchange_full_ready > 0"
                                        type="button"
                                        @click="loadForm.quantity = balances.exchange_full_ready"
                                        class="text-xs font-semibold text-[#1E1545] hover:underline cursor-pointer"
                                    >
                                        Todos
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-center gap-2.5">
                                <button
                                    type="button"
                                    @click="loadForm.quantity = Math.max(1, (Number(loadForm.quantity) || 1) - 1)"
                                    :disabled="loadForm.quantity <= 1 || balances.exchange_full_ready <= 0 || loadForm.processing"
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#F4F3F8] text-[#292638] hover:text-[#1E1545] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar carga"
                                >
                                    <i class="fa-solid fa-minus"></i>
                                </button>

                                <div class="flex-1 max-w-[130px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#1E1545] px-2.5 py-2">
                                    <input
                                        id="load-qty"
                                        v-model.number="loadForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, balances.exchange_full_ready)"
                                        :disabled="balances.exchange_full_ready <= 0"
                                        class="w-full text-center text-2xl font-bold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#A6A3B2]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="loadForm.quantity = Math.min(Math.max(1, balances.exchange_full_ready), (Number(loadForm.quantity) || 1) + 1)"
                                    :disabled="loadForm.quantity >= balances.exchange_full_ready || balances.exchange_full_ready <= 0 || loadForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#DAD6E8]/75 hover:bg-[#1E1545] text-[#1E1545] hover:text-white border border-[#1E1545]/15 active:scale-95 transition-all flex items-center justify-center text-base disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar carga"
                                >
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="loadForm.processing || balances.exchange_full_ready <= 0"
                            class="w-full min-h-[48px] px-4 rounded-xl bg-[#1E1545] hover:bg-[#2B205E] text-white font-semibold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:cursor-not-allowed"
                        >
                            <i v-if="loadForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <i v-else class="fa-solid fa-truck-ramp-box text-xs"></i>
                            <span class="tabular-nums">
                                {{ balances.exchange_full_ready > 0 ? `Subir ${loadForm.quantity} al camión` : 'Sin llenos disponibles' }}
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 3. HISTORIAL RECIENTE DE MOVIMIENTOS                                  -->
            <!-- ===================================================================== -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[#E9E7F0] flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-[#292638]">Movimientos recientes</h2>
                        <p class="text-xs text-[#747181] mt-0.5">Últimas entradas, llenados y cargas de cambios</p>
                    </div>

                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-[#F4F3F8] text-[#747181] border border-[#E9E7F0] tabular-nums">
                        {{ recentExchangeMovements.length }} registro{{ recentExchangeMovements.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <!-- Estado vacío -->
                <div v-if="recentExchangeMovements.length === 0" class="py-12 px-6 text-center">
                    <div class="h-12 w-12 mx-auto rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] flex items-center justify-center text-[#1E1545] mb-3">
                        <i class="fa-solid fa-arrows-rotate text-lg"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-[#292638]">Sin movimientos registrados</h3>
                    <p class="text-xs text-[#747181] mt-1 max-w-xs mx-auto">
                        Cuando recibas vacíos, llenes garrafones o los subas al camión, aparecerán aquí.
                    </p>
                </div>

                <template v-else>
                    <!-- Tabla escritorio/tablet -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#F4F3F8] border-b border-[#E9E7F0] text-xs font-medium text-[#747181]">
                                    <th class="py-3 px-6">Fecha</th>
                                    <th class="py-3 px-4">Movimiento</th>
                                    <th class="py-3 px-4">Cantidad</th>
                                    <th class="py-3 px-6">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E9E7F0]/75 text-sm">
                                <tr v-for="mov in recentExchangeMovements" :key="mov.id" class="hover:bg-[#F4F3F8]/60 transition-colors">
                                    <td class="py-3.5 px-6 whitespace-nowrap text-xs text-[#747181] tabular-nums">
                                        {{ mov.date }}
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold', getMovementMeta(mov.type).badge]">
                                            <i :class="['fa-solid text-[10px]', getMovementMeta(mov.type).icon]"></i>
                                            {{ getMovementMeta(mov.type).label }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap text-xs font-bold text-[#292638] tabular-nums">
                                        {{ mov.quantity }} botellón{{ mov.quantity === 1 ? '' : 'es' }}
                                    </td>
                                    <td class="py-3.5 px-6 text-xs text-[#747181]">
                                        {{ mov.notes || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Lista compacta en móvil -->
                    <div class="sm:hidden divide-y divide-[#E9E7F0]/75">
                        <div v-for="mov in recentExchangeMovements" :key="mov.id" class="px-5 py-3.5 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold', getMovementMeta(mov.type).badge]">
                                    <i :class="['fa-solid text-[9px]', getMovementMeta(mov.type).icon]"></i>
                                    {{ getMovementMeta(mov.type).label }}
                                </span>
                                <p class="text-[11px] text-[#747181] mt-1 tabular-nums truncate">
                                    {{ mov.date }}
                                </p>
                            </div>
                            <span class="text-sm font-bold text-[#292638] tabular-nums shrink-0">
                                {{ mov.quantity }} u.
                            </span>
                        </div>
                    </div>
                </template>
            </div>

        </div>

        <!-- Modal simple para reportar dañado o sin localizar -->
        <div
            v-if="showInconvenienceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/35 backdrop-blur-[2px]"
            @click.self="showInconvenienceModal = false"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-[0_20px_50px_-12px_rgba(30,21,69,0.22)] border border-[#E9E7F0] space-y-4">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-xl bg-[#F8F0DF] text-[#A97827] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#292638]">Reportar botellón dañado o perdido</h3>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Se descontará de los activos y pasará a revisión de Administración.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitInconvenience" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[#747181] mb-1">¿Dónde estaba?</label>
                            <select
                                v-model="inconvenienceForm.origin_stage"
                                class="w-full rounded-xl border-[#DAD6E8] text-xs font-medium text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                            >
                                <option value="plant_empty">Vacío en Planta ({{ balances.exchange_empty_available }})</option>
                                <option value="plant_full">Lleno en Planta ({{ balances.exchange_full_ready }})</option>
                                <option value="truck_full">Lleno en Camión ({{ truckSummary.exchange_full_available }})</option>
                                <option value="truck_empty">Vacío en Camión ({{ truckSummary.exchange_empty_collected }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#747181] mb-1">Cantidad</label>
                            <input
                                v-model.number="inconvenienceForm.quantity"
                                type="number"
                                min="1"
                                required
                                class="w-full rounded-xl border-[#DAD6E8] text-xs font-semibold text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8] tabular-nums"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-[#747181] mb-1">Motivo</label>
                            <select
                                v-model="inconvenienceForm.reason"
                                class="w-full rounded-xl border-[#DAD6E8] text-xs font-medium text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                            >
                                <option value="rotura">Rotura / golpe</option>
                                <option value="fisura">Fisura o fuga</option>
                                <option value="contaminacion">Contaminación</option>
                                <option value="extraviado">Extraviado / sin localizar</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-[#747181] mb-1">Estado</label>
                            <select
                                v-model="inconvenienceForm.location_status"
                                class="w-full rounded-xl border-[#DAD6E8] text-xs font-medium text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                            >
                                <option value="separado">Separado (dañado)</option>
                                <option value="sin_localizar">Sin localizar</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-[#747181] mb-1">Observación (opcional)</label>
                        <textarea
                            v-model="inconvenienceForm.notes"
                            rows="2"
                            placeholder="Ej. Se quebró al bajar del camión..."
                            class="w-full rounded-xl border-[#DAD6E8] text-xs text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8]"
                        ></textarea>
                    </div>

                    <div v-if="Object.keys(inconvenienceForm.errors).length > 0" class="p-2.5 rounded-xl bg-[#FCECEF] text-xs font-medium text-[#E82147]">
                        {{ Object.values(inconvenienceForm.errors)[0] }}
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-[#E9E7F0]">
                        <button
                            type="button"
                            @click="showInconvenienceModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-[#747181] bg-[#F4F3F8] hover:bg-[#E9E7F0] border border-[#E9E7F0] transition-colors cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="inconvenienceForm.processing"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#E82147] hover:bg-[#CF1B3E] transition-colors cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2]"
                        >
                            {{ inconvenienceForm.processing ? 'Guardando...' : 'Reportar incidente' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
