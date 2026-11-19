<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowRight,
    ArrowUpFromLine,
    Check,
    ClipboardCheck,
    Droplets,
    Factory,
    Loader2,
    Minus,
    Pencil,
    Plus,
    RefreshCw,
    Scale,
    Truck,
    X,
} from 'lucide-vue-next';

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
const targetDifference = computed(() => activeTotal.value - targetQuantity.value);
const targetProgressPct = computed(() => {
    if (targetQuantity.value <= 0) return 100;
    return Math.min(100, Math.max(0, Math.round((activeTotal.value / targetQuantity.value) * 100)));
});

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
// AJUSTAR / REINICIAR CONTEO COMPLETO (PLANTA + CAMIÓN)
// =========================================================================
const showRecountModal = ref(false);
const recountForm = useForm({
    empty_count: props.balances.exchange_empty_available ?? 0,
    washing_count: 0,
    full_count: props.balances.exchange_full_ready ?? 0,
    truck_full_count: props.truckSummary.exchange_full_available ?? 0,
    truck_empty_count: props.truckSummary.exchange_empty_collected ?? 0,
    reason: 'Ajuste de inventario físico',
    notes: '',
    sync_target: false,
});

const openRecountModal = () => {
    recountForm.empty_count = Number(props.balances.exchange_empty_available ?? 0);
    recountForm.washing_count = 0;
    recountForm.full_count = Number(props.balances.exchange_full_ready ?? 0);
    recountForm.truck_full_count = Number(props.truckSummary.exchange_full_available ?? 0);
    recountForm.truck_empty_count = Number(props.truckSummary.exchange_empty_collected ?? 0);
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
    return (
        Math.max(0, Number(recountForm.empty_count || 0)) +
        Math.max(0, Number(recountForm.full_count || 0)) +
        Number(props.balances.exchange_full_reserved || 0)
    );
});

const recountTruckTotal = computed(() => {
    return (
        Math.max(0, Number(recountForm.truck_full_count || 0)) +
        Math.max(0, Number(recountForm.truck_empty_count || 0))
    );
});

const recountActiveTotal = computed(() => recountPlantTotal.value + recountTruckTotal.value);

const submitRecount = () => {
    if (recountForm.processing) return;
    recountForm.post(route('planta.inventory.reconcile'), {
        preserveScroll: true,
        onSuccess: () => {
            showRecountModal.value = false;
        },
    });
};

// =========================================================================
// SUMAR NUEVOS (SOLO ADMINISTRADOR)
// =========================================================================
const showNewBottlesModal = ref(false);
const newBottlesForm = useForm({
    quantity: 2,
    bottle_condition: 'empty',
    reason: 'Compra de lote nuevo',
    notes: '',
    sync_target: true,
});

const openNewBottlesModal = () => {
    newBottlesForm.quantity = 2;
    newBottlesForm.bottle_condition = 'empty';
    newBottlesForm.reason = 'Compra de lote nuevo';
    newBottlesForm.notes = '';
    newBottlesForm.sync_target = true;
    newBottlesForm.clearErrors();
    showNewBottlesModal.value = true;
};

const submitNewBottles = () => {
    if (newBottlesForm.processing) return;
    newBottlesForm.post(route('admin.exchange-inventory.new-bottles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showNewBottlesModal.value = false;
        },
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
            return { label: 'Vacíos recibidos', badge: 'bg-[#E9F3F8] text-[#0071BA] border-[#0071BA]/15', kind: 'receive' };
        case 'plant_washing_finished':
            return { label: 'Llenado en planta', badge: 'bg-[#E8F2EC] text-[#26845B] border-[#26845B]/15', kind: 'fill' };
        case 'truck_load_exchange':
            return { label: 'Subida a camión', badge: 'bg-[#EEEAF7] text-[#1E1545] border-[#1E1545]/15', kind: 'load' };
        case 'plant_washing_started':
            return { label: 'Inicio de lavado', badge: 'bg-[#F4F3F8] text-[#747181] border-[#E9E7F0]', kind: 'wash' };
        case 'plant_physical_recount':
            return { label: 'Ajuste / reinicio', badge: 'bg-[#E9F3F8] text-[#0071BA] border-[#0071BA]/15', kind: 'other' };
        case 'exchange_new_entry':
            return { label: 'Alta de nuevos', badge: 'bg-[#E8F2EC] text-[#26845B] border-[#26845B]/15', kind: 'fill' };
        default:
            return { label: 'Movimiento', badge: 'bg-[#F4F3F8] text-[#747181] border-[#E9E7F0]', kind: 'other' };
    }
};
</script>

<template>
    <Head title="Cambios 20L" />

    <AuthenticatedLayout>
        <div class="py-5 sm:py-7 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Alerta de error de negocio -->
            <div
                v-if="formError"
                class="px-4 py-3.5 rounded-2xl bg-[#FCECEF] border border-[#F6C8D2] text-[#292638] flex items-center gap-3"
                role="alert"
            >
                <div class="h-9 w-9 rounded-xl bg-[#E82147] text-white flex items-center justify-center shrink-0">
                    <AlertTriangle class="w-4 h-4" />
                </div>
                <p class="text-sm font-semibold text-[#B91C3C] flex-1">{{ formError }}</p>
            </div>

            <!-- ===================================================================== -->
            <!-- ENCABEZADO DE MÓDULO                                                  -->
            <!-- ===================================================================== -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_1px_3px_rgba(30,21,69,0.04)] px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="h-11 w-11 rounded-2xl bg-[#1E1545] text-white flex items-center justify-center shrink-0 shadow-xs">
                        <RefreshCw class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-lg sm:text-xl font-bold text-[#292638] tracking-[-0.015em] truncate">
                            Control de botellones de cambio
                        </h1>
                        <p class="text-xs text-[#747181] mt-0.5 flex items-center gap-1.5 flex-wrap">
                            <span>Circuito cerrado entre Planta y</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-[#F4F3F8] text-[#1E1545] font-semibold">
                                <Truck class="w-3 h-3" />
                                {{ truckSummary.driver_name }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto shrink-0">
                    <button
                        type="button"
                        @click="openInconvenienceModal"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-[#FEF5E6] hover:bg-[#FDEBC8] text-[#9A670E] border border-[#F5DFB5] text-xs font-semibold transition-colors cursor-pointer min-h-[40px]"
                    >
                        <AlertTriangle class="w-3.5 h-3.5" />
                        <span>Reportar dañado / perdido</span>
                    </button>

                    <button
                        type="button"
                        @click="openRecountModal"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-[#F4F3F8] text-[#1E1545] border border-[#E9E7F0] text-xs font-semibold transition-colors cursor-pointer min-h-[40px]"
                    >
                        <Pencil class="w-3.5 h-3.5 text-[#0071BA]" />
                        <span>Ajustar / Reiniciar</span>
                    </button>

                    <button
                        v-if="isAdmin"
                        type="button"
                        @click="openNewBottlesModal"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-semibold transition-colors cursor-pointer min-h-[40px]"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Sumar nuevos</span>
                    </button>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- CONTEO INICIAL FÍSICO (COMPACTO, SOLO SI ESTÁ PENDIENTE)              -->
            <!-- ===================================================================== -->
            <div
                v-if="initialCountPending"
                class="bg-[#FFFDF8] rounded-2xl border border-[#F0D9A8] p-4 sm:p-5 shadow-[0_2px_10px_-6px_rgba(169,120,39,0.18)]"
            >
                <form @submit.prevent="submitInitialCount" class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="h-10 w-10 rounded-xl bg-[#FEF5E6] border border-[#F5DFB5] text-[#A97827] flex items-center justify-center shrink-0">
                            <ClipboardCheck class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-sm font-bold text-[#292638]">Conteo inicial físico en Planta</h2>
                                <span class="px-2 py-0.5 rounded-full bg-[#FEF5E6] text-[#9A670E] border border-[#F5DFB5] text-[10px] font-bold uppercase tracking-wider">
                                    Pendiente
                                </span>
                            </div>
                            <p class="text-xs text-[#747181] mt-0.5">
                                Captura cuántos botellones hay físicamente en Planta hoy para fijar el inventario base.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-end gap-2.5 shrink-0">
                        <div class="w-full sm:w-36">
                            <label class="block text-[11px] font-semibold text-[#747181] mb-1">Vacíos en planta</label>
                            <input
                                v-model.number="initialCountForm.empty_count"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-xl bg-white border-[#E4D5B3] text-sm font-bold text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8] tabular-nums py-2"
                            />
                        </div>
                        <div class="w-full sm:w-36">
                            <label class="block text-[11px] font-semibold text-[#747181] mb-1">Llenos en planta</label>
                            <input
                                v-model.number="initialCountForm.full_count"
                                type="number"
                                min="0"
                                required
                                class="w-full rounded-xl bg-white border-[#E4D5B3] text-sm font-bold text-[#292638] focus:border-[#1E1545] focus:ring-[#DAD6E8] tabular-nums py-2"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="initialCountForm.processing"
                            class="min-h-[40px] px-4 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white text-xs font-bold transition-colors cursor-pointer whitespace-nowrap disabled:opacity-50"
                        >
                            {{ initialCountForm.processing ? 'Guardando...' : 'Guardar conteo' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- ===================================================================== -->
            <!-- 1. RESUMEN DEL CIRCUITO (PLANTA · CAMIÓN · BALANCE VS META)           -->
            <!-- ===================================================================== -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- En Planta -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] p-5 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-[#747181] flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-[#0071BA]"></span>
                                En Planta
                            </span>
                            <div class="mt-1 text-3xl font-extrabold text-[#292638] tracking-tight tabular-nums">
                                {{ plantTotal }}
                            </div>
                        </div>
                        <div class="h-11 w-11 rounded-2xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                            <Factory class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-[#F4F3F8] text-xs font-semibold tabular-nums">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#F4F3F8] text-[#292638]">
                            {{ balances.exchange_empty_available }} vacíos
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#E8F2EC] text-[#26845B]">
                            {{ balances.exchange_full_ready }} llenos
                        </span>
                    </div>
                </div>

                <!-- En Camión -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] p-5 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-[#747181] flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-[#1E1545]"></span>
                                En Camión
                            </span>
                            <div class="mt-1 text-3xl font-extrabold text-[#292638] tracking-tight tabular-nums">
                                {{ truckTotal }}
                            </div>
                        </div>
                        <div class="h-11 w-11 rounded-2xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                            <Truck class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-[#F4F3F8] text-xs font-semibold tabular-nums">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#E8F2EC] text-[#26845B]">
                            {{ truckSummary.exchange_full_available }} llenos
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#E9F3F8] text-[#0071BA]">
                            {{ truckSummary.exchange_empty_collected }} vacíos
                        </span>
                    </div>
                </div>

                <!-- Total Circuito (Meta visible solo para Administrador) -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] p-5 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-semibold text-[#747181] flex items-center gap-1.5">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="(!isAdmin || isTargetExact) ? 'bg-[#26845B]' : 'bg-[#A97827]'"
                                ></span>
                                Total en circuito
                            </span>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-3xl font-extrabold text-[#292638] tracking-tight tabular-nums">
                                    {{ activeTotal }}
                                </span>
                                <span v-if="isAdmin" class="text-xs font-semibold text-[#747181] tabular-nums">
                                    / {{ targetQuantity }} meta
                                </span>
                                <span v-else class="text-xs font-semibold text-[#747181]">
                                    botellones
                                </span>
                            </div>
                        </div>
                        <div
                            class="h-11 w-11 rounded-2xl flex items-center justify-center shrink-0"
                            :class="(!isAdmin || isTargetExact) ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-[#FEF5E6] text-[#A97827]'"
                        >
                            <Scale class="w-5 h-5" />
                        </div>
                    </div>

                    <div v-if="isAdmin" class="space-y-1.5 pt-2 border-t border-[#F4F3F8]">
                        <div class="flex items-center justify-between text-[11px] font-semibold">
                            <span :class="isTargetExact ? 'text-[#26845B]' : 'text-[#9A670E]'">
                                <template v-if="isTargetExact">Cuadra al 100% con la meta</template>
                                <template v-else-if="targetDifference < 0">Faltan {{ Math.abs(targetDifference) }} para la meta</template>
                                <template v-else>+{{ targetDifference }} sobre la meta</template>
                            </span>
                            <span class="text-[#747181] tabular-nums">{{ targetProgressPct }}%</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-[#F4F3F8] overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-300"
                                :class="isTargetExact ? 'bg-[#26845B]' : 'bg-[#D99A27]'"
                                :style="{ width: `${targetProgressPct}%` }"
                            ></div>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between pt-2 border-t border-[#F4F3F8] text-xs text-[#747181]">
                        <span>Planta {{ plantTotal }} + Camión {{ truckTotal }}</span>
                        <button type="button" @click="openRecountModal" class="font-bold text-[#0071BA] hover:text-[#1E1545] cursor-pointer">
                            Ajustar
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 2. FLUJO EN 3 PASOS: RECIBIR → LLENAR → SUBIR AL CAMIÓN              -->
            <!-- ===================================================================== -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-stretch">

                <!-- PASO 1: RECIBIR VACÍOS DEL CAMIÓN -->
                <div
                    class="rounded-2xl border flex flex-col overflow-hidden transition-all"
                    :class="truckSummary.exchange_empty_collected > 0
                        ? 'bg-white border-[#0071BA]/30 shadow-[0_6px_24px_-10px_rgba(0,113,186,0.2)]'
                        : 'bg-[#FAFAFC] border-[#E9E7F0]'"
                >
                    <div class="h-1 w-full" :class="truckSummary.exchange_empty_collected > 0 ? 'bg-[#0071BA]' : 'bg-[#E9E7F0]'"></div>

                    <div class="px-5 py-4 border-b border-[#E9E7F0]/80 flex items-center justify-between gap-3 bg-white">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="h-9 w-9 rounded-xl font-bold text-xs flex items-center justify-center shrink-0"
                                :class="truckSummary.exchange_empty_collected > 0 ? 'bg-[#0071BA] text-white' : 'bg-[#E9F3F8] text-[#0071BA]'"
                            >
                                1
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Recibir vacíos</h2>
                                <p class="text-[11px] text-[#747181] flex items-center gap-1 truncate">
                                    <span>Camión</span>
                                    <ArrowRight class="w-3 h-3 shrink-0" />
                                    <span>Planta</span>
                                </p>
                            </div>
                        </div>

                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-bold tabular-nums shrink-0"
                            :class="truckSummary.exchange_empty_collected > 0 ? 'bg-[#E9F3F8] text-[#0071BA]' : 'bg-[#F4F3F8] text-[#747181]'"
                        >
                            {{ truckSummary.exchange_empty_collected }} disp.
                        </span>
                    </div>

                    <form @submit.prevent="submitReceiveEmpties" class="p-5 flex-1 flex flex-col justify-between gap-4">
                        <div class="rounded-2xl bg-[#F4F3F8]/80 border border-[#E9E7F0] p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <label for="receive-qty" class="text-xs font-semibold text-[#747181]">
                                    Cantidad a recibir
                                </label>
                                <button
                                    v-if="truckSummary.exchange_empty_collected > 0"
                                    type="button"
                                    @click="receiveForm.quantity = truckSummary.exchange_empty_collected"
                                    class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-white text-[#0071BA] border border-[#0071BA]/25 hover:bg-[#E9F3F8] transition-colors cursor-pointer"
                                >
                                    Todos ({{ truckSummary.exchange_empty_collected }})
                                </button>
                            </div>

                            <div class="flex items-center justify-center gap-2.5">
                                <button
                                    type="button"
                                    @click="receiveForm.quantity = Math.max(1, (Number(receiveForm.quantity) || 1) - 1)"
                                    :disabled="receiveForm.quantity <= 1 || truckSummary.exchange_empty_collected <= 0 || receiveForm.processing"
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#E9F3F8] text-[#292638] hover:text-[#0071BA] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar vacío"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>

                                <div class="flex-1 max-w-[128px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#0071BA] px-2.5 py-2">
                                    <input
                                        id="receive-qty"
                                        v-model.number="receiveForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, truckSummary.exchange_empty_collected)"
                                        :disabled="truckSummary.exchange_empty_collected <= 0"
                                        class="w-full text-center text-2xl font-extrabold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#B4B1BE]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="receiveForm.quantity = Math.min(Math.max(1, truckSummary.exchange_empty_collected), (Number(receiveForm.quantity) || 1) + 1)"
                                    :disabled="receiveForm.quantity >= truckSummary.exchange_empty_collected || truckSummary.exchange_empty_collected <= 0 || receiveForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#E9F3F8] hover:bg-[#0071BA] text-[#0071BA] hover:text-white border border-[#0071BA]/25 active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar vacío"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="receiveForm.processing || truckSummary.exchange_empty_collected <= 0"
                            class="w-full min-h-[46px] px-4 rounded-xl bg-[#0071BA] hover:bg-[#005E9B] text-white font-bold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-2xs disabled:bg-[#ECEAF2] disabled:text-[#9F9CAC] disabled:shadow-none disabled:cursor-not-allowed"
                        >
                            <Loader2 v-if="receiveForm.processing" class="w-4 h-4 animate-spin" />
                            <ArrowDownToLine v-else class="w-4 h-4" />
                            <span class="tabular-nums">
                                {{ truckSummary.exchange_empty_collected > 0 ? `Recibir ${receiveForm.quantity} vacío(s)` : 'Sin vacíos en camión' }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- PASO 2: LLENAR CAMBIOS EN PLANTA -->
                <div
                    class="rounded-2xl border flex flex-col overflow-hidden transition-all"
                    :class="balances.exchange_empty_available > 0
                        ? 'bg-white border-[#26845B]/30 shadow-[0_6px_24px_-10px_rgba(38,132,91,0.2)]'
                        : 'bg-[#FAFAFC] border-[#E9E7F0]'"
                >
                    <div class="h-1 w-full" :class="balances.exchange_empty_available > 0 ? 'bg-[#26845B]' : 'bg-[#E9E7F0]'"></div>

                    <div class="px-5 py-4 border-b border-[#E9E7F0]/80 flex items-center justify-between gap-3 bg-white">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="h-9 w-9 rounded-xl font-bold text-xs flex items-center justify-center shrink-0"
                                :class="balances.exchange_empty_available > 0 ? 'bg-[#26845B] text-white' : 'bg-[#E8F2EC] text-[#26845B]'"
                            >
                                2
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Llenar cambios</h2>
                                <p class="text-[11px] text-[#747181] flex items-center gap-1 truncate">
                                    <span>Vacíos</span>
                                    <ArrowRight class="w-3 h-3 shrink-0" />
                                    <span>Llenos en Planta</span>
                                </p>
                            </div>
                        </div>

                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-bold tabular-nums shrink-0"
                            :class="balances.exchange_empty_available > 0 ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-[#F4F3F8] text-[#747181]'"
                        >
                            {{ balances.exchange_empty_available }} vacíos
                        </span>
                    </div>

                    <form @submit.prevent="submitFill" class="p-5 flex-1 flex flex-col justify-between gap-4">
                        <div class="rounded-2xl bg-[#F4F3F8]/80 border border-[#E9E7F0] p-4 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <label for="fill-qty" class="text-xs font-semibold text-[#747181]">
                                    Cantidad llenada
                                </label>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        v-for="preset in [5, 10]"
                                        :key="preset"
                                        v-show="balances.exchange_empty_available >= preset"
                                        type="button"
                                        @click="fillForm.quantity = preset"
                                        class="text-xs font-bold px-2 py-0.5 rounded-full border transition-colors cursor-pointer"
                                        :class="fillForm.quantity === preset
                                            ? 'bg-[#26845B] text-white border-[#26845B]'
                                            : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                    >
                                        {{ preset }}
                                    </button>
                                    <button
                                        v-if="balances.exchange_empty_available > 0"
                                        type="button"
                                        @click="fillForm.quantity = balances.exchange_empty_available"
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-white text-[#26845B] border border-[#26845B]/25 hover:bg-[#E8F2EC] transition-colors cursor-pointer"
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
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#E8F2EC] text-[#292638] hover:text-[#26845B] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar llenado"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>

                                <div class="flex-1 max-w-[128px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#26845B] px-2.5 py-2">
                                    <input
                                        id="fill-qty"
                                        v-model.number="fillForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, balances.exchange_empty_available)"
                                        :disabled="balances.exchange_empty_available <= 0"
                                        class="w-full text-center text-2xl font-extrabold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#B4B1BE]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="fillForm.quantity = Math.min(Math.max(1, balances.exchange_empty_available), (Number(fillForm.quantity) || 1) + 1)"
                                    :disabled="fillForm.quantity >= balances.exchange_empty_available || balances.exchange_empty_available <= 0 || fillForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#E8F2EC] hover:bg-[#26845B] text-[#26845B] hover:text-white border border-[#26845B]/25 active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar llenado"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="fillForm.processing || balances.exchange_empty_available <= 0"
                            class="w-full min-h-[46px] px-4 rounded-xl bg-[#26845B] hover:bg-[#1F6D4B] text-white font-bold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-2xs disabled:bg-[#ECEAF2] disabled:text-[#9F9CAC] disabled:shadow-none disabled:cursor-not-allowed"
                        >
                            <Loader2 v-if="fillForm.processing" class="w-4 h-4 animate-spin" />
                            <Droplets v-else class="w-4 h-4" />
                            <span class="tabular-nums">
                                {{ balances.exchange_empty_available > 0 ? `Llenar ${fillForm.quantity} botellón(es)` : 'En espera de vacíos' }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- PASO 3: SUBIR LLENOS AL CAMIÓN -->
                <div
                    class="rounded-2xl border flex flex-col overflow-hidden transition-all"
                    :class="balances.exchange_full_ready > 0
                        ? 'bg-white border-[#1E1545]/25 shadow-[0_6px_24px_-10px_rgba(30,21,69,0.2)]'
                        : 'bg-[#FAFAFC] border-[#E9E7F0]'"
                >
                    <div class="h-1 w-full" :class="balances.exchange_full_ready > 0 ? 'bg-[#1E1545]' : 'bg-[#E9E7F0]'"></div>

                    <div class="px-5 py-4 border-b border-[#E9E7F0]/80 flex items-center justify-between gap-3 bg-white">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="h-9 w-9 rounded-xl font-bold text-xs flex items-center justify-center shrink-0"
                                :class="balances.exchange_full_ready > 0 ? 'bg-[#1E1545] text-white' : 'bg-[#EEEAF7] text-[#1E1545]'"
                            >
                                3
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-[#292638] truncate">Subir al camión</h2>
                                <p class="text-[11px] text-[#747181] flex items-center gap-1 truncate">
                                    <span>Planta</span>
                                    <ArrowRight class="w-3 h-3 shrink-0" />
                                    <span class="truncate">{{ truckSummary.driver_name }}</span>
                                </p>
                            </div>
                        </div>

                        <span
                            class="px-2.5 py-1 rounded-full text-xs font-bold tabular-nums shrink-0"
                            :class="balances.exchange_full_ready > 0 ? 'bg-[#EEEAF7] text-[#1E1545]' : 'bg-[#F4F3F8] text-[#747181]'"
                        >
                            {{ balances.exchange_full_ready }} llenos
                        </span>
                    </div>

                    <form @submit.prevent="submitLoadTruck" class="p-5 flex-1 flex flex-col justify-between gap-4">
                        <div class="rounded-2xl bg-[#F4F3F8]/80 border border-[#E9E7F0] p-4 space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <label for="load-qty" class="text-xs font-semibold text-[#747181]">
                                    Cantidad para camión
                                </label>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        v-for="preset in [5, 10]"
                                        :key="preset"
                                        v-show="balances.exchange_full_ready >= preset"
                                        type="button"
                                        @click="loadForm.quantity = preset"
                                        class="text-xs font-bold px-2 py-0.5 rounded-full border transition-colors cursor-pointer"
                                        :class="loadForm.quantity === preset
                                            ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                            : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                    >
                                        {{ preset }}
                                    </button>
                                    <button
                                        v-if="balances.exchange_full_ready > 0"
                                        type="button"
                                        @click="loadForm.quantity = balances.exchange_full_ready"
                                        class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-white text-[#1E1545] border border-[#1E1545]/20 hover:bg-[#EEEAF7] transition-colors cursor-pointer"
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
                                    class="h-12 w-12 rounded-xl bg-white hover:bg-[#EEEAF7] text-[#292638] hover:text-[#1E1545] border border-[#DAD6E8] active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Restar carga"
                                >
                                    <Minus class="w-4 h-4" />
                                </button>

                                <div class="flex-1 max-w-[128px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#1E1545] px-2.5 py-2">
                                    <input
                                        id="load-qty"
                                        v-model.number="loadForm.quantity"
                                        type="number"
                                        min="1"
                                        :max="Math.max(1, balances.exchange_full_ready)"
                                        :disabled="balances.exchange_full_ready <= 0"
                                        class="w-full text-center text-2xl font-extrabold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums disabled:text-[#B4B1BE]"
                                    />
                                </div>

                                <button
                                    type="button"
                                    @click="loadForm.quantity = Math.min(Math.max(1, balances.exchange_full_ready), (Number(loadForm.quantity) || 1) + 1)"
                                    :disabled="loadForm.quantity >= balances.exchange_full_ready || balances.exchange_full_ready <= 0 || loadForm.processing"
                                    class="h-12 w-12 rounded-xl bg-[#EEEAF7] hover:bg-[#1E1545] text-[#1E1545] hover:text-white border border-[#1E1545]/15 active:scale-95 transition-all flex items-center justify-center disabled:bg-[#EEEDF3] disabled:text-[#B4B1BE] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer shrink-0"
                                    aria-label="Sumar carga"
                                >
                                    <Plus class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="loadForm.processing || balances.exchange_full_ready <= 0"
                            class="w-full min-h-[46px] px-4 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-bold text-sm flex items-center justify-center gap-2 transition-colors cursor-pointer shadow-2xs disabled:bg-[#ECEAF2] disabled:text-[#9F9CAC] disabled:shadow-none disabled:cursor-not-allowed"
                        >
                            <Loader2 v-if="loadForm.processing" class="w-4 h-4 animate-spin" />
                            <ArrowUpFromLine v-else class="w-4 h-4" />
                            <span class="tabular-nums">
                                {{ balances.exchange_full_ready > 0 ? `Subir ${loadForm.quantity} al camión` : 'Sin llenos listos' }}
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 3. HISTORIAL RECIENTE DE MOVIMIENTOS                                  -->
            <!-- ===================================================================== -->
            <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E9E7F0] flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-[#292638]">Movimientos recientes</h2>
                        <p class="text-xs text-[#747181] mt-0.5">Últimas entradas, llenados y cargas de cambios</p>
                    </div>

                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#F4F3F8] text-[#747181] border border-[#E9E7F0] tabular-nums">
                        {{ recentExchangeMovements.length }} registro{{ recentExchangeMovements.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <!-- Estado vacío compacto -->
                <div v-if="recentExchangeMovements.length === 0" class="py-8 px-5 flex flex-col sm:flex-row items-center justify-center gap-3 text-center sm:text-left">
                    <div class="h-11 w-11 rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] flex items-center justify-center text-[#1E1545] shrink-0">
                        <RefreshCw class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-[#292638]">Sin movimientos registrados</h3>
                        <p class="text-xs text-[#747181] mt-0.5">
                            Cuando recibas vacíos, llenes garrafones o los subas al camión, aparecerán aquí.
                        </p>
                    </div>
                </div>

                <template v-else>
                    <!-- Tabla escritorio/tablet -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#F4F3F8] border-b border-[#E9E7F0] text-xs font-semibold text-[#747181]">
                                    <th class="py-3 px-5">Fecha</th>
                                    <th class="py-3 px-4">Movimiento</th>
                                    <th class="py-3 px-4">Cantidad</th>
                                    <th class="py-3 px-5">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E9E7F0]/75 text-sm">
                                <tr v-for="mov in recentExchangeMovements" :key="mov.id" class="hover:bg-[#F4F3F8]/60 transition-colors">
                                    <td class="py-3 px-5 whitespace-nowrap text-xs text-[#747181] tabular-nums">
                                        {{ mov.date }}
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold', getMovementMeta(mov.type).badge]">
                                            <ArrowDownToLine v-if="getMovementMeta(mov.type).kind === 'receive'" class="w-3 h-3" />
                                            <Droplets v-else-if="getMovementMeta(mov.type).kind === 'fill'" class="w-3 h-3" />
                                            <ArrowUpFromLine v-else-if="getMovementMeta(mov.type).kind === 'load'" class="w-3 h-3" />
                                            <Check v-else class="w-3 h-3" />
                                            {{ getMovementMeta(mov.type).label }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap text-xs font-bold text-[#292638] tabular-nums">
                                        {{ mov.quantity }} botellón{{ mov.quantity === 1 ? '' : 'es' }}
                                    </td>
                                    <td class="py-3 px-5 text-xs text-[#747181]">
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
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border text-[11px] font-semibold', getMovementMeta(mov.type).badge]">
                                    <ArrowDownToLine v-if="getMovementMeta(mov.type).kind === 'receive'" class="w-3 h-3" />
                                    <Droplets v-else-if="getMovementMeta(mov.type).kind === 'fill'" class="w-3 h-3" />
                                    <ArrowUpFromLine v-else-if="getMovementMeta(mov.type).kind === 'load'" class="w-3 h-3" />
                                    <Check v-else class="w-3 h-3" />
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

        <!-- Modal para reportar dañado o sin localizar -->
        <div
            v-if="showInconvenienceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/40 backdrop-blur-[2px]"
            @click.self="showInconvenienceModal = false"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-[0_20px_50px_-12px_rgba(30,21,69,0.22)] border border-[#E9E7F0] space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#FEF5E6] text-[#9A670E] border border-[#F5DFB5] flex items-center justify-center shrink-0">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[#292638]">Reportar botellón dañado o perdido</h3>
                            <p class="text-xs text-[#747181] mt-0.5">
                                Se descontará de los activos y pasará a revisión de Administración.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showInconvenienceModal = false"
                        class="h-8 w-8 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#747181] flex items-center justify-center cursor-pointer shrink-0"
                    >
                        <X class="w-4 h-4" />
                    </button>
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

        <!-- Modal: Ajustar / Reiniciar conteo (Planta y Camión) -->
        <div
            v-if="showRecountModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs"
            @click.self="showRecountModal = false"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-5 sm:p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Ajustar o reiniciar conteo</h3>
                        <p class="text-xs text-[#747181]">Sube, baja o reinicia cuántos botellones hay en Planta y Camión</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="resetRecountToZero"
                            class="px-2.5 py-1 rounded-lg bg-[#FCECEF] hover:bg-[#F9D6DE] text-[#E82147] text-[11px] font-bold cursor-pointer"
                        >
                            Poner en 0
                        </button>
                        <button @click="showRecountModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submitRecount" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#0071BA]">En Planta</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.empty_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.full_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-[#F8F7FC] border border-[#E9E7F0] space-y-2.5">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#1E1545]">En Camión</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Llenos *</label>
                                    <input v-model.number="recountForm.truck_full_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-[#747181] mb-1">Vacíos *</label>
                                    <input v-model.number="recountForm.truck_empty_count" type="number" min="0" max="10000" required class="w-full h-10 text-base font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] p-3 text-xs space-y-1 tabular-nums">
                        <div class="flex justify-between text-[#747181]">
                            <span>Planta:</span>
                            <strong class="text-[#1E1545]">{{ plantTotal }} → {{ recountPlantTotal }} u.</strong>
                        </div>
                        <div class="flex justify-between text-[#747181]">
                            <span>Camión:</span>
                            <strong class="text-[#1E1545]">{{ truckTotal }} → {{ recountTruckTotal }} u.</strong>
                        </div>
                        <div class="pt-1 border-t border-[#E9E7F0] flex justify-between font-bold text-[#1E1545] text-sm">
                            <span>Total circuito:</span>
                            <span>{{ activeTotal }} → {{ recountActiveTotal }} u.</span>
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

                    <label v-if="isAdmin && recountActiveTotal > 0" class="flex items-start gap-2.5 p-3 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] cursor-pointer">
                        <input v-model="recountForm.sync_target" type="checkbox" class="mt-0.5 rounded border-[#E9E7F0] text-[#0071BA]" />
                        <span class="text-xs text-[#292638]">
                            <strong class="block text-[#1E1545]">Actualizar la Meta al nuevo total</strong>
                            Cambiar meta a {{ recountActiveTotal }} u.
                        </span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showRecountModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="recountForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer">Guardar ajuste</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Sumar botellones nuevos (Solo Administrador) -->
        <div
            v-if="showNewBottlesModal && isAdmin"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs"
            @click.self="showNewBottlesModal = false"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Sumar botellones nuevos</h3>
                        <p class="text-xs text-[#747181]">Ingreso físico de cambios de 20 L en Planta</p>
                    </div>
                    <button @click="showNewBottlesModal = false" type="button" class="text-[#747181] hover:text-[#1E1545] cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitNewBottles" class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold uppercase text-[#747181]">Cantidad *</label>
                            <div class="flex items-center gap-1">
                                <button
                                    v-for="q in [1, 2, 5, 10]"
                                    :key="q"
                                    type="button"
                                    @click="newBottlesForm.quantity = q"
                                    class="px-2 py-0.5 rounded-md text-[11px] font-bold border transition cursor-pointer"
                                    :class="Number(newBottlesForm.quantity) === q
                                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                        : 'bg-[#F4F3F8] text-[#1E1545] border-[#E9E7F0] hover:bg-[#EEEAF7]'"
                                >
                                    +{{ q }}
                                </button>
                            </div>
                        </div>
                        <input v-model.number="newBottlesForm.quantity" type="number" min="1" max="5000" required class="w-full h-11 text-lg font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            @click="newBottlesForm.bottle_condition = 'empty'"
                            class="p-3 rounded-xl border text-left text-xs transition cursor-pointer"
                            :class="newBottlesForm.bottle_condition === 'empty' ? 'bg-[#E5F4FD] border-[#0071BA] text-[#1E1545] font-bold' : 'border-[#E9E7F0] text-[#747181]'"
                        >
                            Vacío apto
                        </button>
                        <button
                            type="button"
                            @click="newBottlesForm.bottle_condition = 'full'"
                            class="p-3 rounded-xl border text-left text-xs transition cursor-pointer"
                            :class="newBottlesForm.bottle_condition === 'full' ? 'bg-[#E6F7EE] border-[#26845B] text-[#26845B] font-bold' : 'border-[#E9E7F0] text-[#747181]'"
                        >
                            Lleno listo
                        </button>
                    </div>

                    <label class="flex items-start gap-2.5 p-3 rounded-xl bg-[#F4F3F8] border border-[#E9E7F0] cursor-pointer">
                        <input v-model="newBottlesForm.sync_target" type="checkbox" class="mt-0.5 rounded border-[#E9E7F0] text-[#1E1545]" />
                        <span class="text-xs text-[#292638]">
                            <strong class="block text-[#1E1545]">Sumar también a la Meta</strong>
                            Subirá de {{ targetQuantity }} a {{ targetQuantity + (Number(newBottlesForm.quantity) || 0) }} u.
                        </span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showNewBottlesModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="newBottlesForm.processing" class="h-10 px-5 rounded-xl bg-[#1E1545] hover:bg-[#292059] text-white text-xs font-bold cursor-pointer">Confirmar alta</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
