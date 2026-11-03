<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    inventoryState: {
        type: Object,
        required: true,
    },
    customersWithFixed: {
        type: Array,
        default: () => [],
    },
    canRecordInitialCount: {
        type: Boolean,
        default: false,
    },
    canReportIncident: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

// ----------------------------------------------------
// Formulario de Conteo Inicial Físico
// ----------------------------------------------------
const showInitialCountConfirmModal = ref(false);
const initialCountForm = useForm({
    empty_count: '',
    full_count: '',
    notes: '',
});

const openInitialCountConfirmation = () => {
    if (initialCountForm.empty_count === '' || initialCountForm.full_count === '') {
        alert('Por favor complete las cantidades de botellones vacíos y llenos.');
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
// Modal y Formulario de Registro de Incidencias
// ----------------------------------------------------
const showIncidentModal = ref(false);
const incidentForm = useForm({
    bottle_type: 'cambio',
    customer_id: '',
    order_item_id: '',
    quantity: 1,
    stage: 'lavado',
    damage_type: 'fisura',
    notes: '',
});

const openIncidentModalForFixed = (fixedItem = null) => {
    if (fixedItem) {
        incidentForm.bottle_type = 'fijo';
        incidentForm.customer_id = fixedItem.customer_id;
        incidentForm.order_item_id = fixedItem.order_item_id;
        incidentForm.stage = fixedItem.in_washing > 0 ? 'lavado' : 'almacen';
        incidentForm.quantity = 1;
    } else {
        incidentForm.bottle_type = 'cambio';
        incidentForm.customer_id = '';
        incidentForm.order_item_id = '';
        incidentForm.stage = 'lavado';
        incidentForm.quantity = 1;
    }
    incidentForm.damage_type = 'fisura';
    incidentForm.notes = '';
    showIncidentModal.value = true;
};

const submitIncident = () => {
    incidentForm.post(route('planta.inventory.incidents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showIncidentModal.value = false;
            incidentForm.reset();
        },
    });
};

// ----------------------------------------------------
// Operaciones de Producción (Iniciar / Finalizar Lavado)
// ----------------------------------------------------
const showStartWashingModal = ref(false);
const startWashingForm = useForm({
    quantity: 1,
});

const submitStartWashing = () => {
    startWashingForm.post(route('planta.inventory.production.start'), {
        preserveScroll: true,
        onSuccess: () => {
            showStartWashingModal.value = false;
            startWashingForm.reset();
        },
    });
};

const showFinishWashingModal = ref(false);
const finishWashingForm = useForm({
    quantity: 1,
});

const submitFinishWashing = () => {
    finishWashingForm.post(route('planta.inventory.production.finish'), {
        preserveScroll: true,
        onSuccess: () => {
            showFinishWashingModal.value = false;
            finishWashingForm.reset();
        },
    });
};

// Formateador de fechas
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Traductor de tipos de daño
const translateDamageType = (type) => {
    const map = {
        rotura: 'Rotura o quebrado',
        fisura: 'Fisura o fuga',
        contaminacion: 'Contaminación severa',
        deformacion: 'Deformación por calor',
        otro: 'Otro defecto físico',
    };
    return map[type] || type;
};

// Traductor de etapas
const translateStage = (stage) => {
    const map = {
        recepcion: 'Recepción',
        lavado: 'Lavado / Desinfección',
        llenado: 'Llenado',
        almacen: 'Almacén de Llenos',
        carga: 'Carga a Camión',
    };
    return map[stage] || stage;
};
</script>

<template>
    <Head title="Existencias e Inventario de Planta - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800">
                        Existencias e Inventario de Planta
                    </h2>
                    <p class="text-sm text-slate-500">
                        Control físico de garrafones de cambio, fijos por cliente e incidencias
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canReportIncident"
                        type="button"
                        @click="openIncidentModalForFixed(null)"
                        class="inline-flex items-center px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs sm:text-sm rounded-xl shadow-xs transition-colors"
                    >
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                        Reportar Incidencia
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- FLASH MESSAGES -->
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm font-semibold flex items-center justify-between"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- SECCIÓN 1: CONTEO INICIAL FÍSICO PENDIENTE -->
                <div
                    v-if="inventoryState.initial_count_pending"
                    class="rounded-2xl bg-amber-50/90 border-2 border-amber-300 p-6 shadow-sm"
                >
                    <div class="max-w-3xl">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-200 text-amber-900 uppercase tracking-wide">
                                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>
                                Acción Requerida
                            </span>
                            <h3 class="text-lg font-bold text-amber-950">
                                Captura de Existencias Iniciales de Planta
                            </h3>
                        </div>
                        <p class="text-sm text-amber-900/80 leading-relaxed">
                            Para iniciar las operaciones del módulo de Planta es obligatorio capturar el conteo físico presencial de garrafones de 20 litros (vacíos y llenos) presentes en el establecimiento.
                            <strong>Esta acción es definitiva, define la línea base y solo se realiza una vez.</strong>
                        </p>

                        <!-- Formulario de Conteo Inicial -->
                        <form
                            v-if="canRecordInitialCount"
                            @submit.prevent="openInitialCountConfirmation"
                            class="mt-6 bg-white p-5 rounded-xl border border-amber-200 shadow-2xs space-y-4"
                        >
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                        Garrafones Vacíos de 20L
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model.number="initialCountForm.empty_count"
                                            type="number"
                                            min="0"
                                            step="1"
                                            required
                                            placeholder="0"
                                            class="w-full rounded-xl border-slate-300 pl-10 text-slate-900 font-bold focus:border-amber-500 focus:ring-amber-500"
                                        />
                                        <div class="absolute left-3.5 top-2.5 text-slate-400">
                                            <i class="fa-solid fa-box-open"></i>
                                        </div>
                                    </div>
                                    <span v-if="initialCountForm.errors.empty_count" class="text-xs text-rose-600 mt-1 block">
                                        {{ initialCountForm.errors.empty_count }}
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                        Garrafones Llenos de 20L
                                    </label>
                                    <div class="relative">
                                        <input
                                            v-model.number="initialCountForm.full_count"
                                            type="number"
                                            min="0"
                                            step="1"
                                            required
                                            placeholder="0"
                                            class="w-full rounded-xl border-slate-300 pl-10 text-slate-900 font-bold focus:border-amber-500 focus:ring-amber-500"
                                        />
                                        <div class="absolute left-3.5 top-2.5 text-slate-400">
                                            <i class="fa-solid fa-bottle-water"></i>
                                        </div>
                                    </div>
                                    <span v-if="initialCountForm.errors.full_count" class="text-xs text-rose-600 mt-1 block">
                                        {{ initialCountForm.errors.full_count }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                    Observaciones o Notas de Apertura (Opcional)
                                </label>
                                <textarea
                                    v-model="initialCountForm.notes"
                                    rows="2"
                                    maxlength="500"
                                    placeholder="Detalles sobre el estado de la planta, calibración o auditoría..."
                                    class="w-full rounded-xl border-slate-300 text-sm text-slate-800 focus:border-amber-500 focus:ring-amber-500"
                                ></textarea>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button
                                    type="submit"
                                    :disabled="initialCountForm.processing"
                                    class="inline-flex items-center px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm shadow-sm transition-colors"
                                >
                                    <i class="fa-solid fa-clipboard-check mr-2"></i>
                                    Verificar y Registrar Conteo Inicial
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN 1 (ALT): INFORMACIÓN DEL CONTEO INICIAL COMPLETADO -->
                <div
                    v-else-if="inventoryState.initial_count_info"
                    class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-xs sm:text-sm text-slate-600 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2"
                >
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
                        <span>
                            <strong>Línea base registrada:</strong>
                            {{ inventoryState.initial_count_info.empty_count }} vacíos y {{ inventoryState.initial_count_info.full_count }} llenos
                            (Registrado por {{ inventoryState.initial_count_info.operator_name }} el {{ inventoryState.initial_count_info.completed_at }}).
                        </span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-slate-200 text-slate-700 uppercase">
                        Conteo Cerrado
                    </span>
                </div>

                <!-- SECCIÓN 2: SALDOS REALES DE PLANTA (EXCLUYENTES) -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-cubes-stacked text-blue-600"></i>
                            Saldos Operativos de Planta (Garrafones de Cambio 20L)
                        </h3>
                        <div class="flex items-center gap-2">
                            <!-- Acciones rápidas de prueba/operación de producción -->
                            <button
                                v-if="!inventoryState.initial_count_pending && inventoryState.balances.exchange_empty_available > 0"
                                @click="showStartWashingModal = true"
                                type="button"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 text-xs font-bold transition-colors"
                            >
                                <i class="fa-solid fa-soap mr-1.5"></i>
                                Iniciar Lavado
                            </button>
                            <button
                                v-if="!inventoryState.initial_count_pending && inventoryState.balances.exchange_washing_filling > 0"
                                @click="showFinishWashingModal = true"
                                type="button"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-900 text-xs font-bold transition-colors"
                            >
                                <i class="fa-solid fa-check mr-1.5"></i>
                                Finalizar Llenado
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4">
                        <!-- Vacíos Disponibles -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-500 uppercase">Vacíos Disp.</span>
                                <i class="fa-solid fa-box-open text-blue-500 text-base"></i>
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                                {{ inventoryState.balances.exchange_empty_available }}
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Esperando entrar a lavado</p>
                        </div>

                        <!-- En Lavado / Llenado -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-amber-600 uppercase">En Proceso</span>
                                <i class="fa-solid fa-soap text-amber-500 text-base"></i>
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-amber-600">
                                {{ inventoryState.balances.exchange_washing_filling }}
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">En túnel de purificación</p>
                        </div>

                        <!-- Llenos Disponibles -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-emerald-700 uppercase">Llenos Disp.</span>
                                <i class="fa-solid fa-bottle-water text-emerald-600 text-base"></i>
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-700">
                                {{ inventoryState.balances.exchange_full_available }}
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Aptos para reparto</p>
                        </div>

                        <!-- Reservados Camión -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-2xs">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-purple-700 uppercase">Reservados</span>
                                <i class="fa-solid fa-truck-ramp-box text-purple-600 text-base"></i>
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-purple-700">
                                {{ inventoryState.balances.exchange_full_reserved }}
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Propuestos a chofer</p>
                        </div>

                        <!-- Merma / En Incidencia -->
                        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-2xs col-span-2 md:col-span-1">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-rose-700 uppercase">En Incidencia</span>
                                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-rose-700">
                                {{ inventoryState.balances.exchange_damaged_total }}
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Separados por daño físico</p>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: BOTELLONES FIJOS POR CLIENTE EN PLANTA -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <i class="fa-solid fa-user-tag text-emerald-600"></i>
                                Botellones Fijos de Clientes en Planta
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Identificados exclusivamente por propietario y pedido. Totalmente separados del inventario genérico.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 self-start sm:self-auto border border-emerald-200">
                            {{ inventoryState.fixed_in_plant_by_customer.length }} clientes en proceso
                        </span>
                    </div>

                    <div v-if="inventoryState.fixed_in_plant_by_customer.length === 0" class="p-8 text-center text-sm text-slate-400">
                        <i class="fa-regular fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                        No hay botellones fijos de clientes actualmente bajo custodia en planta.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Cliente Propietario</th>
                                    <th class="px-4 py-3">Pedido</th>
                                    <th class="px-4 py-3 text-center">Recibidos</th>
                                    <th class="px-4 py-3 text-center">En Lavado</th>
                                    <th class="px-4 py-3 text-center">Listos Llenos</th>
                                    <th class="px-4 py-3 text-center">Dañados</th>
                                    <th class="px-4 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="item in inventoryState.fixed_in_plant_by_customer"
                                    :key="item.order_item_id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">
                                            {{ item.customer_nickname || item.customer_name || 'Sin apodo' }}
                                        </div>
                                        <div class="text-xs text-slate-400 font-mono">
                                            {{ item.customer_code }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-xs text-blue-700">
                                        {{ item.order_folio }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-800">
                                        {{ item.received_at_plant }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-amber-600">
                                        {{ item.in_washing }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-emerald-700">
                                        {{ item.ready_full }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            v-if="item.damaged > 0"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800"
                                        >
                                            {{ item.damaged }}
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            @click="openIncidentModalForFixed(item)"
                                            class="inline-flex items-center px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold transition-colors"
                                            title="Reportar daño en envase fijo"
                                        >
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                            Reportar daño
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN 4: INCIDENCIAS Y ENVASES DAÑADOS -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                                Bitácora de Incidencias y Envases Dañados
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Envases dañados separados por rotura, fisura o falla de material. La resolución de la incidencia corresponde a Administración.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                            {{ inventoryState.incidents.length }} registradas
                        </span>
                    </div>

                    <div v-if="inventoryState.incidents.length === 0" class="p-8 text-center text-sm text-slate-400">
                        <i class="fa-solid fa-shield-halved text-3xl mb-2 text-slate-300 block"></i>
                        No se han reportado incidencias físicas en planta.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Envase</th>
                                    <th class="px-4 py-3">Propietario / Pedido</th>
                                    <th class="px-4 py-3">Etapa</th>
                                    <th class="px-4 py-3">Daño</th>
                                    <th class="px-4 py-3 text-center">Cant.</th>
                                    <th class="px-4 py-3">Reportado Por</th>
                                    <th class="px-4 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="inc in inventoryState.incidents"
                                    :key="inc.id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                                        {{ formatDate(inc.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="inc.bottle_type === 'fijo' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider"
                                        >
                                            {{ inc.bottle_type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="inc.customer">
                                            <span class="font-bold text-slate-900">
                                                {{ inc.customer.nickname || inc.customer.name }}
                                            </span>
                                            <span class="text-xs text-slate-400 block font-mono">
                                                {{ inc.customer.code }}
                                            </span>
                                        </div>
                                        <div v-else class="text-xs text-slate-400 italic">
                                            Fondo de planta (Cambio)
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-700 font-medium">
                                        {{ translateStage(inc.stage) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-rose-700 font-semibold">
                                        {{ translateDamageType(inc.damage_type) }}
                                        <span v-if="inc.notes" class="block text-[11px] text-slate-400 font-normal truncate max-w-xs" :title="inc.notes">
                                            {{ inc.notes }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-rose-600">
                                        {{ inc.quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600">
                                        {{ inc.reported_by_user?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="inc.status === 'pendiente_resolucion'"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800"
                                        >
                                            Pendiente Admin
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800"
                                        >
                                            {{ inc.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN 5: HISTORIAL DE MOVIMIENTOS EN PLANTA -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                            Historial de Movimientos de Custodia en Planta
                        </h3>
                    </div>

                    <div v-if="inventoryState.recent_movements.length === 0" class="p-8 text-center text-sm text-slate-400">
                        No hay movimientos registrados.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-700">
                            <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Fecha</th>
                                    <th class="px-4 py-3">Tipo Movimiento</th>
                                    <th class="px-4 py-3">Origen &rarr; Destino</th>
                                    <th class="px-4 py-3 text-center">Cant.</th>
                                    <th class="px-4 py-3">Operador / Confirmó</th>
                                    <th class="px-4 py-3">Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <tr
                                    v-for="mov in inventoryState.recent_movements"
                                    :key="mov.id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        {{ formatDate(mov.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-800 capitalize">
                                        {{ mov.movement_type.replace(/_/g, ' ') }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-mono">
                                        {{ mov.origin }} &rarr; {{ mov.destination }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-900">
                                        {{ mov.quantity }} u.
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ mov.confirmed_by_user?.name || mov.user?.name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 truncate max-w-xs" :title="mov.notes">
                                        {{ mov.notes || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: CONFIRMACIÓN DEL CONTEO INICIAL FÍSICO                -->
        <!-- ============================================================= -->
        <div
            v-if="showInitialCountConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="h-12 w-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-xl font-bold">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h4 class="text-lg font-bold text-slate-900">
                        Confirmar Línea Base de Planta
                    </h4>
                    <p class="text-sm text-slate-600 mt-1">
                        Estás a punto de establecer las existencias físicas iniciales de la planta en:
                    </p>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Garrafones Vacíos:</span>
                        <strong class="text-slate-900">{{ initialCountForm.empty_count }} u.</strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Garrafones Llenos:</span>
                        <strong class="text-slate-900">{{ initialCountForm.full_count }} u.</strong>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-slate-200 font-bold">
                        <span class="text-slate-700">Total Físico Base:</span>
                        <span class="text-blue-700">{{ Number(initialCountForm.empty_count) + Number(initialCountForm.full_count) }} u.</span>
                    </div>
                </div>

                <p class="text-xs text-rose-600 font-medium">
                    <i class="fa-solid fa-circle-exclamation mr-1"></i>
                    Esta acción solo se puede realizar una vez y no puede duplicarse ni revertirse directamente.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="showInitialCountConfirmModal = false"
                        class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="submitInitialCount"
                        :disabled="initialCountForm.processing"
                        class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-sm shadow-sm transition-colors"
                    >
                        Confirmar y Establecer
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: REGISTRAR INCIDENCIA DE DAÑO EN ENVASE                -->
        <!-- ============================================================= -->
        <div
            v-if="showIncidentModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
                        Registrar Incidencia de Daño
                    </h4>
                    <button
                        @click="showIncidentModal = false"
                        class="text-slate-400 hover:text-slate-600 text-lg"
                    >
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submitIncident" class="space-y-4 text-sm">
                    <!-- Tipo de Envase -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Tipo de Envase
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                :class="incidentForm.bottle_type === 'cambio' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200'"
                                class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer font-semibold text-slate-800"
                            >
                                <input
                                    v-model="incidentForm.bottle_type"
                                    type="radio"
                                    value="cambio"
                                    class="text-blue-600 focus:ring-blue-500"
                                />
                                <span>Cambio (Fondo Planta)</span>
                            </label>
                            <label
                                :class="incidentForm.bottle_type === 'fijo' ? 'border-purple-600 bg-purple-50/50' : 'border-slate-200'"
                                class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer font-semibold text-slate-800"
                            >
                                <input
                                    v-model="incidentForm.bottle_type"
                                    type="radio"
                                    value="fijo"
                                    class="text-purple-600 focus:ring-purple-500"
                                />
                                <span>Fijo (Del Cliente)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Si es Fijo: Selección de Cliente Propietario -->
                    <div v-if="incidentForm.bottle_type === 'fijo'">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cliente Propietario *
                        </label>
                        <select
                            v-model="incidentForm.customer_id"
                            required
                            class="w-full rounded-xl border-slate-300 text-slate-800 focus:border-rose-500 focus:ring-rose-500 text-sm"
                        >
                            <option value="">-- Seleccionar cliente con fijos en planta --</option>
                            <option
                                v-for="c in customersWithFixed"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.nickname || c.name || 'Sin apodo' }} ({{ c.code }})
                            </option>
                        </select>
                        <span v-if="incidentForm.errors.customer_id" class="text-xs text-rose-600 mt-1 block">
                            {{ incidentForm.errors.customer_id }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <!-- Cantidad -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                Cantidad Dañada *
                            </label>
                            <input
                                v-model.number="incidentForm.quantity"
                                type="number"
                                min="1"
                                step="1"
                                required
                                class="w-full rounded-xl border-slate-300 font-bold text-slate-900 focus:border-rose-500 focus:ring-rose-500 text-sm"
                            />
                            <span v-if="incidentForm.errors.quantity" class="text-xs text-rose-600 mt-1 block">
                                {{ incidentForm.errors.quantity }}
                            </span>
                        </div>

                        <!-- Etapa Operativa -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                                Etapa de Detección *
                            </label>
                            <select
                                v-model="incidentForm.stage"
                                required
                                class="w-full rounded-xl border-slate-300 text-slate-800 focus:border-rose-500 focus:ring-rose-500 text-sm"
                            >
                                <option value="recepcion">Recepción</option>
                                <option value="lavado">Lavado / Desinfección</option>
                                <option value="llenado">Llenado</option>
                                <option value="almacen">Almacén de Llenos</option>
                                <option value="carga">Carga a Camión</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tipo de Daño -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Tipo de Daño Físico *
                        </label>
                        <select
                            v-model="incidentForm.damage_type"
                            required
                            class="w-full rounded-xl border-slate-300 text-slate-800 focus:border-rose-500 focus:ring-rose-500 text-sm"
                        >
                            <option value="fisura">Fisura o fuga en cuerpo/fondo</option>
                            <option value="rotura">Rotura o quebrado visible</option>
                            <option value="contaminacion">Contaminación severa / olor a combustible o químicos</option>
                            <option value="deformacion">Deformación por calor o aplastamiento</option>
                            <option value="otro">Otro defecto físico</option>
                        </select>
                    </div>

                    <!-- Descripción / Notas -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Descripción del Hallazgo *
                        </label>
                        <textarea
                            v-model="incidentForm.notes"
                            rows="3"
                            required
                            minlength="5"
                            maxlength="1000"
                            placeholder="Detalle exactamente cómo y dónde se detectó el daño físico..."
                            class="w-full rounded-xl border-slate-300 text-slate-800 text-sm focus:border-rose-500 focus:ring-rose-500"
                        ></textarea>
                        <span v-if="incidentForm.errors.notes" class="text-xs text-rose-600 mt-1 block">
                            {{ incidentForm.errors.notes }}
                        </span>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900">
                        <i class="fa-solid fa-info-circle mr-1 text-amber-700"></i>
                        Los envases serán separados inmediatamente de los disponibles. No se alteran los conteos personales del cliente ni se genera cobro de rellenado.
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showIncidentModal = false"
                            class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="incidentForm.processing"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-sm transition-colors"
                        >
                            Registrar y Separar Envases
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: INICIAR LAVADO DE CAMBIOS                             -->
        <!-- ============================================================= -->
        <div
            v-if="showStartWashingModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <h4 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-soap text-amber-600"></i>
                    Ingresar Lote a Lavado
                </h4>
                <p class="text-xs text-slate-500">
                    Pasa botellones vacíos de planta a la línea de lavado y purificación.
                </p>
                <form @submit.prevent="submitStartWashing" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad a Lavar (Máx: {{ inventoryState.balances.exchange_empty_available }})
                        </label>
                        <input
                            v-model.number="startWashingForm.quantity"
                            type="number"
                            min="1"
                            :max="inventoryState.balances.exchange_empty_available"
                            required
                            class="w-full rounded-xl border-slate-300 font-bold text-slate-900 text-sm focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="showStartWashingModal = false" class="px-3 py-1.5 text-xs font-semibold text-slate-600">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="startWashingForm.processing" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold">
                            Iniciar Lavado
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL: FINALIZAR LLENADO DE CAMBIOS                          -->
        <!-- ============================================================= -->
        <div
            v-if="showFinishWashingModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <h4 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-bottle-water text-emerald-600"></i>
                    Finalizar Llenado y Sellado
                </h4>
                <p class="text-xs text-slate-500">
                    Pasa botellones de la línea de lavado al almacén de llenos listos.
                </p>
                <form @submit.prevent="submitFinishWashing" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">
                            Cantidad Llenada y Sellada (Máx: {{ inventoryState.balances.exchange_washing_filling }})
                        </label>
                        <input
                            v-model.number="finishWashingForm.quantity"
                            type="number"
                            min="1"
                            :max="inventoryState.balances.exchange_washing_filling"
                            required
                            class="w-full rounded-xl border-slate-300 font-bold text-slate-900 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="showFinishWashingModal = false" class="px-3 py-1.5 text-xs font-semibold text-slate-600">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="finishWashingForm.processing" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                            Finalizar y Almacenar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

