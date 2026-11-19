<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    AlertTriangle,
    CheckCircle2,
    Plus,
    X,
} from 'lucide-vue-next';

const props = defineProps({
    inventoryState: { type: Object, required: true },
    customersWithFixed: { type: Array, default: () => [] },
    canReportIncident: { type: Boolean, default: false },
});

const page = usePage();
const incidentFilter = ref('pendientes'); // 'pendientes' | 'resueltas' | 'todas'

const allIncidents = computed(() => props.inventoryState?.incidents || []);
const pendingIncidents = computed(() =>
    allIncidents.value.filter((i) => i.status === 'pendiente_resolucion')
);
const resolvedIncidents = computed(() =>
    allIncidents.value.filter((i) => i.status !== 'pendiente_resolucion')
);
const filteredIncidents = computed(() => {
    if (incidentFilter.value === 'pendientes') return pendingIncidents.value;
    if (incidentFilter.value === 'resueltas') return resolvedIncidents.value;
    return allIncidents.value;
});

const totalUnlocated = computed(() => Number(props.inventoryState?.balances?.exchange_unlocated || 0));
const totalOpenIssues = computed(() => pendingIncidents.value.length + (totalUnlocated.value > 0 ? 1 : 0));

// Reporte unificado de incidencia
const showIncidentModal = ref(false);
const incidentPlace = ref('plant_empty');

const incidentForm = useForm({
    bottle_type: 'cambio',
    customer_id: '',
    order_item_id: '',
    quantity: 1,
    stage: 'almacen_vacio',
    bottle_condition: 'empty',
    location_status: 'separado',
    damage_type: 'rotura',
    notes: '',
});

const openIncidentModal = () => {
    incidentForm.clearErrors();
    incidentForm.bottle_type = 'cambio';
    incidentForm.customer_id = '';
    incidentForm.order_item_id = '';
    const b = props.inventoryState?.balances || {};
    incidentPlace.value = Number(b.exchange_empty_available || 0) > 0 ? 'plant_empty' : 'plant_full';
    incidentForm.stage = 'almacen_vacio';
    incidentForm.bottle_condition = 'empty';
    incidentForm.location_status = 'separado';
    incidentForm.quantity = 1;
    incidentForm.damage_type = 'rotura';
    incidentForm.notes = '';
    showIncidentModal.value = true;
};

const syncExchangePlace = () => {
    if (incidentForm.bottle_type !== 'cambio') return;
    if (incidentPlace.value === 'plant_empty') {
        incidentForm.stage = 'almacen_vacio';
        incidentForm.bottle_condition = 'empty';
    } else if (incidentPlace.value === 'plant_full') {
        incidentForm.stage = 'almacen';
        incidentForm.bottle_condition = 'full';
    } else if (incidentPlace.value === 'truck_full') {
        incidentForm.stage = 'en_camion';
        incidentForm.bottle_condition = 'full';
    } else {
        incidentForm.stage = 'en_camion';
        incidentForm.bottle_condition = 'empty';
    }
};

watch(incidentPlace, syncExchangePlace);
watch(() => incidentForm.bottle_type, (type) => {
    if (type === 'cambio') {
        incidentForm.customer_id = '';
        incidentForm.order_item_id = '';
        syncExchangePlace();
    } else {
        incidentForm.location_status = 'separado';
        incidentForm.stage = 'recepcion';
    }
});

const submitIncident = () => {
    if (incidentForm.processing) return;
    syncExchangePlace();
    incidentForm.post(route('planta.inventory.incidents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showIncidentModal.value = false;
            incidentFilter.value = 'pendientes';
            incidentForm.reset();
        },
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const translateDamageType = (type) => {
    const map = {
        rotura: 'Roto / quebrado',
        fisura: 'Fisura o fuga',
        contaminacion: 'Contaminado',
        deformacion: 'Deformado',
        otro: 'Otro',
    };
    return map[type] || type;
};

const translateStage = (stage) => {
    const map = {
        recepcion: 'Planta (Vacíos)',
        almacen_vacio: 'Planta (Vacíos)',
        lavado: 'Planta',
        llenado: 'Planta',
        almacen: 'Planta (Llenos)',
        almacen_planta: 'Planta (Llenos)',
        preparado: 'Planta (Listos)',
        carga: 'Carga a camión',
        carga_camion: 'Carga a camión',
        en_camion: 'En camión',
        ruta: 'En ruta',
    };
    return map[stage] || stage;
};
</script>

<template>
    <Head title="Incidencias - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- ENCABEZADO -->
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#D99A27] text-white flex items-center justify-center shadow-sm">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1E1545]">
                                Incidencias
                            </h1>
                            <p class="text-xs sm:text-sm text-[#747181]">
                                Reportes de botellones dañados o sin localizar
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="canReportIncident"
                        type="button"
                        @click="openIncidentModal"
                        class="h-10 px-4 rounded-xl bg-[#E82147] hover:bg-[#C91A3C] text-white font-bold text-xs sm:text-sm inline-flex items-center gap-2 shadow-sm transition-colors cursor-pointer self-start sm:self-auto"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Reportar incidencia</span>
                    </button>
                </header>

                <!-- Errores globales -->
                <div
                    v-if="Object.keys(page.props.errors || {}).length > 0"
                    class="p-4 rounded-2xl bg-[#FCECEF] border border-[#E82147]/25 text-[#9F1239] flex items-center gap-3"
                    role="alert"
                >
                    <AlertTriangle class="w-5 h-5 text-[#E82147] shrink-0" />
                    <div class="text-xs sm:text-sm font-semibold">
                        <p v-for="(err, key) in page.props.errors" :key="key">{{ err }}</p>
                    </div>
                </div>

                <!-- 3 KPIS COMPACTOS -->
                <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Pendientes Admin</p>
                            <p class="mt-1 text-2xl sm:text-3xl font-bold tabular-nums" :class="totalOpenIssues > 0 ? 'text-[#D99A27]' : 'text-[#26845B]'">
                                {{ totalOpenIssues }}
                            </p>
                        </div>
                        <div class="h-10 w-10 rounded-xl flex items-center justify-center" :class="totalOpenIssues > 0 ? 'bg-[#FEF3C7] text-[#B45309]' : 'bg-[#E6F7EE] text-[#26845B]'">
                            <AlertTriangle v-if="totalOpenIssues > 0" class="w-5 h-5" />
                            <CheckCircle2 v-else class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Sin localizar</p>
                            <p class="mt-1 text-2xl sm:text-3xl font-bold text-[#1E1545] tabular-nums">{{ totalUnlocated }}</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-[#F4F3F8] text-xs font-bold text-[#747181]">En revisión</span>
                    </div>

                    <div class="rounded-2xl border border-[#E9E7F0] bg-white p-5 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#747181]">Resueltas</p>
                            <p class="mt-1 text-2xl sm:text-3xl font-bold text-[#26845B] tabular-nums">{{ resolvedIncidents.length }}</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-[#E6F7EE] text-[#26845B] flex items-center justify-center">
                            <CheckCircle2 class="w-5 h-5" />
                        </div>
                    </div>
                </section>

                <!-- LISTA DE INCIDENCIAS -->
                <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#F4F3F8]">
                        <h2 class="text-base font-bold text-[#1E1545]">Bitácora de incidencias</h2>

                        <div class="flex items-center gap-1.5 bg-[#F4F3F8] p-1 rounded-xl self-start sm:self-auto">
                            <button
                                type="button"
                                @click="incidentFilter = 'pendientes'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                :class="incidentFilter === 'pendientes' ? 'bg-white text-[#1E1545] shadow-2xs' : 'text-[#747181] hover:text-[#1E1545]'"
                            >
                                Pendientes ({{ pendingIncidents.length }})
                            </button>
                            <button
                                type="button"
                                @click="incidentFilter = 'resueltas'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                :class="incidentFilter === 'resueltas' ? 'bg-white text-[#1E1545] shadow-2xs' : 'text-[#747181] hover:text-[#1E1545]'"
                            >
                                Resueltas ({{ resolvedIncidents.length }})
                            </button>
                            <button
                                type="button"
                                @click="incidentFilter = 'todas'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                :class="incidentFilter === 'todas' ? 'bg-white text-[#1E1545] shadow-2xs' : 'text-[#747181] hover:text-[#1E1545]'"
                            >
                                Todas
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="totalUnlocated > 0 && incidentFilter !== 'resueltas'"
                        class="p-3.5 rounded-xl bg-[#FEF3C7]/60 border border-[#D99A27]/30 flex items-center gap-2 text-xs text-[#92400E] font-semibold"
                    >
                        <AlertTriangle class="w-4 h-4 shrink-0" />
                        <span>Hay {{ totalUnlocated }} botellón(es) de cambio sin localizar pendientes de resolución por Administración.</span>
                    </div>

                    <div v-if="filteredIncidents.length === 0" class="py-10 text-center space-y-1.5">
                        <CheckCircle2 class="w-7 h-7 text-[#26845B] mx-auto" />
                        <p class="text-sm font-bold text-[#1E1545]">
                            {{ incidentFilter === 'pendientes' ? 'Sin incidencias pendientes' : 'No hay incidencias en esta vista' }}
                        </p>
                        <p class="text-xs text-[#747181]">Usa "Reportar incidencia" si detectas algún botellón dañado o faltante.</p>
                    </div>

                    <div v-else class="divide-y divide-[#F4F3F8]">
                        <article
                            v-for="inc in filteredIncidents"
                            :key="inc.id"
                            class="py-4 flex flex-col sm:flex-row sm:items-start justify-between gap-3"
                        >
                            <div class="space-y-1.5 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-[11px] font-bold uppercase"
                                        :class="inc.bottle_type === 'fijo' ? 'bg-[#EEEAF7] text-[#1E1545]' : 'bg-[#E5F4FD] text-[#0071BA]'"
                                    >
                                        {{ inc.bottle_type === 'fijo' ? 'Fijo' : 'Cambio' }}
                                    </span>
                                    <span class="text-xs sm:text-sm font-bold text-[#1E1545] tabular-nums">
                                        {{ inc.quantity }} u. · {{ translateDamageType(inc.damage_type) }}
                                    </span>
                                    <span class="text-xs text-[#747181]">· {{ translateStage(inc.stage) }}</span>
                                </div>

                                <p v-if="inc.customer" class="text-xs font-semibold text-[#1E1545]">
                                    Cliente: {{ inc.customer.nickname || inc.customer.name }}
                                    <span v-if="inc.order?.folio" class="font-mono text-[#0071BA] ml-1">{{ inc.order.folio }}</span>
                                </p>

                                <p v-if="inc.notes" class="text-xs text-[#292638] leading-relaxed">{{ inc.notes }}</p>

                                <p v-if="inc.status !== 'pendiente_resolucion' && inc.resolution_notes" class="text-xs text-[#26845B] font-medium">
                                    Resolución: {{ inc.resolution_notes }}
                                    <span v-if="inc.resolved_by_user?.name" class="text-[#747181]">({{ inc.resolved_by_user.name }})</span>
                                </p>
                            </div>

                            <div class="flex sm:flex-col items-center sm:items-end justify-between gap-1.5 shrink-0">
                                <span
                                    class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold"
                                    :class="inc.status === 'pendiente_resolucion'
                                        ? 'bg-[#FEF3C7] text-[#B45309]'
                                        : 'bg-[#E6F7EE] text-[#26845B]'"
                                >
                                    {{ inc.status === 'pendiente_resolucion' ? 'Pendiente Admin' : 'Resuelta' }}
                                </span>
                                <span class="text-[11px] text-[#747181] tabular-nums">
                                    {{ inc.reported_by_user?.name || 'Operador' }} · {{ formatDate(inc.created_at) }}
                                </span>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>

        <!-- MODAL: REPORTAR INCIDENCIA -->
        <div v-if="showIncidentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/45 backdrop-blur-xs" @click.self="showIncidentModal = false">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-[#E9E7F0] space-y-4">
                <div class="flex items-center justify-between border-b border-[#F4F3F8] pb-3">
                    <div>
                        <h3 class="text-base font-bold text-[#1E1545]">Reportar incidencia</h3>
                        <p class="text-xs text-[#747181]">Separa botellones dañados o sin localizar</p>
                    </div>
                    <button type="button" @click="showIncidentModal = false" class="text-[#747181] hover:text-[#1E1545] cursor-pointer"><X class="w-4 h-4" /></button>
                </div>

                <form @submit.prevent="submitIncident" class="space-y-3.5">
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            @click="incidentForm.bottle_type = 'cambio'"
                            class="p-2.5 rounded-xl border text-xs font-bold transition cursor-pointer"
                            :class="incidentForm.bottle_type === 'cambio' ? 'bg-[#E5F4FD] border-[#0071BA] text-[#1E1545]' : 'border-[#E9E7F0] text-[#747181]'"
                        >
                            Botellón de Cambio
                        </button>
                        <button
                            type="button"
                            @click="incidentForm.bottle_type = 'fijo'"
                            class="p-2.5 rounded-xl border text-xs font-bold transition cursor-pointer"
                            :class="incidentForm.bottle_type === 'fijo' ? 'bg-[#EEEAF7] border-[#1E1545] text-[#1E1545]' : 'border-[#E9E7F0] text-[#747181]'"
                        >
                            Botellón Fijo
                        </button>
                    </div>

                    <div v-if="incidentForm.bottle_type === 'fijo'">
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Cliente propietario *</label>
                        <select v-model="incidentForm.customer_id" required class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                            <option value="">Selecciona cliente...</option>
                            <option v-for="cust in customersWithFixed" :key="cust.id" :value="cust.id">
                                {{ cust.nickname || cust.name }}
                            </option>
                        </select>
                        <p v-if="incidentForm.errors.customer_id" class="text-xs text-[#E82147] mt-1">{{ incidentForm.errors.customer_id }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div v-if="incidentForm.bottle_type === 'cambio'">
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Dónde ocurrió *</label>
                            <select v-model="incidentPlace" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="plant_empty">Vacíos en Planta</option>
                                <option value="plant_full">Llenos en Planta</option>
                                <option value="truck_full">Llenos en Camión</option>
                                <option value="truck_empty">Vacíos en Camión</option>
                            </select>
                        </div>
                        <div v-else>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Etapa *</label>
                            <select v-model="incidentForm.stage" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="recepcion">Planta (Vacíos)</option>
                                <option value="almacen">Planta (Llenos)</option>
                                <option value="en_camion">En Camión</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Cantidad *</label>
                            <input v-model.number="incidentForm.quantity" type="number" min="1" required class="w-full h-10 text-sm font-bold text-[#1E1545] rounded-xl border-[#E9E7F0]" />
                            <p v-if="incidentForm.errors.quantity" class="text-xs text-[#E82147] mt-1">{{ incidentForm.errors.quantity }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3" :class="incidentForm.bottle_type === 'cambio' ? 'grid-cols-2' : 'grid-cols-1'">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Tipo de problema *</label>
                            <select v-model="incidentForm.damage_type" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="rotura">Roto / quebrado</option>
                                <option value="fisura">Fisura o fuga</option>
                                <option value="contaminacion">Contaminado</option>
                                <option value="deformacion">Deformado</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>

                        <div v-if="incidentForm.bottle_type === 'cambio'">
                            <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Estado *</label>
                            <select v-model="incidentForm.location_status" class="w-full h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545]">
                                <option value="separado">Dañado separado</option>
                                <option value="sin_localizar">Sin localizar</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-[#747181] mb-1">Descripción *</label>
                        <input v-model="incidentForm.notes" type="text" required placeholder="Ej: Se fracturó el cuello al descargar" class="w-full h-10 text-xs rounded-xl border-[#E9E7F0]" />
                        <p v-if="incidentForm.errors.notes" class="text-xs text-[#E82147] mt-1">{{ incidentForm.errors.notes }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showIncidentModal = false" class="h-10 px-4 rounded-xl text-xs font-bold text-[#747181] hover:bg-[#F4F3F8] cursor-pointer">Cancelar</button>
                        <button type="submit" :disabled="incidentForm.processing" class="h-10 px-5 rounded-xl bg-[#E82147] hover:bg-[#C91A3C] text-white text-xs font-bold cursor-pointer">Registrar incidencia</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
