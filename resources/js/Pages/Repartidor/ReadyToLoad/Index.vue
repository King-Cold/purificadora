<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    readyItems: {
        type: Array,
        default: () => [],
    },
    totalOrders: {
        type: Number,
        default: 0,
    },
    totalBottles: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.load || page.props.flash?.error || null);

// Modal 1: Aceptar Carga Individual (Total o Parcial)
const showLoadModal = ref(false);
const itemToLoad = ref(null);
const loadForm = useForm({
    order_id: null,
    quantity: 1,
    difference_reason: '',
    notes: '',
});

const openLoadModal = (item) => {
    itemToLoad.value = item;
    loadForm.order_id = item.order_id;
    loadForm.quantity = item.ready_quantity;
    loadForm.difference_reason = '';
    loadForm.notes = '';
    loadForm.clearErrors();
    showLoadModal.value = true;
};

const closeLoadModal = () => {
    showLoadModal.value = false;
    itemToLoad.value = null;
};

const incrementLoadQty = () => {
    if (itemToLoad.value && loadForm.quantity < itemToLoad.value.ready_quantity) {
        loadForm.quantity++;
    }
};

const decrementLoadQty = () => {
    if (loadForm.quantity > 1) {
        loadForm.quantity--;
    }
};

const submitSingleLoad = () => {
    if (!itemToLoad.value || loadForm.processing) return;

    loadForm.post(route('repartidor.ready-to-load.accept'), {
        preserveScroll: true,
        onSuccess: () => closeLoadModal(),
    });
};

// Modal 2: Aceptar Todos los Listos
const showAcceptAllModal = ref(false);
const acceptAllForm = useForm({});

const openAcceptAllModal = () => {
    showAcceptAllModal.value = true;
};

const closeAcceptAllModal = () => {
    showAcceptAllModal.value = false;
};

const submitAcceptAll = () => {
    acceptAllForm.post(route('repartidor.ready-to-load.accept-all'), {
        preserveScroll: true,
        onSuccess: () => closeAcceptAllModal(),
    });
};
</script>

<template>
    <Head title="Listos para cargar · Repartidor Puritronic" />

    <AuthenticatedLayout wide>
        <div class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-4 pb-28 md:pb-16">

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
            <!-- 1. ENCABEZADO Y RESUMEN                                                    -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-4 sm:p-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <Link :href="route('repartidor.reparto')" class="text-blue-900 hover:underline">Reparto</Link>
                            <span>/</span>
                            <span>Carga física</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                            <i class="fa-solid fa-truck-ramp-box text-blue-900"></i> Listos para cargar
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                            Botellones fijos preparados en Planta listos para cotejar y subir físicamente al camión de reparto.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start md:self-center">
                        <Link
                            :href="route('repartidor.deliveries.index')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-900 hover:bg-blue-100 border border-blue-200 transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-dolly"></i> Ir a Por entregar →
                        </Link>
                    </div>
                </div>

                <!-- Barra de acción masiva superior -->
                <div class="pt-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="font-bold">Total listo en Planta:</span>
                        <span class="px-2.5 py-1 rounded-xl bg-slate-100 text-slate-900 font-mono font-black">
                            {{ totalBottles }} botellones ({{ totalOrders }} pedidos)
                        </span>
                    </div>

                    <button
                        v-if="readyItems.length > 0"
                        @click="openAcceptAllModal"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white shadow-2xs transition active:scale-95 min-h-[44px]"
                    >
                        <i class="fa-solid fa-check-double text-sky-400"></i>
                        <span>Aceptar todos ({{ totalBottles }} u.)</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. TABLA COMPACTA: CLIENTE, PEDIDO, FIJOS LISTOS Y ACCIONES               -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div v-if="readyItems.length === 0" class="p-12 text-center space-y-2">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-800">No hay fijos listos para cargar en este momento</p>
                    <p class="text-xs text-slate-400">Cuando el Operador de Planta marque los fijos como llenados, aparecerán aquí.</p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <!-- Cabecera -->
                    <div class="hidden md:grid md:grid-cols-12 gap-3 px-5 py-3 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <div class="col-span-4">Cliente / Propietario</div>
                        <div class="col-span-3">Pedido (Folio)</div>
                        <div class="col-span-2 text-center">Fijos listos (20 L)</div>
                        <div class="col-span-3 text-right">Acciones</div>
                    </div>

                    <!-- Filas -->
                    <div
                        v-for="item in readyItems"
                        :key="item.item_id"
                        class="p-4 sm:px-5 hover:bg-slate-50/60 transition flex flex-col md:grid md:grid-cols-12 gap-3 md:items-center"
                    >
                        <!-- Cliente -->
                        <div class="md:col-span-4 space-y-0.5">
                            <span class="font-black text-slate-900 text-sm sm:text-base block">
                                {{ item.customer.nickname ? `"${item.customer.nickname}"` : item.customer.name }}
                            </span>
                            <span v-if="item.customer.nickname && item.customer.name" class="text-xs text-slate-500 block">
                                {{ item.customer.name }}
                            </span>
                            <p class="text-xs text-slate-400 line-clamp-1">
                                <i class="fa-solid fa-location-dot mr-1 text-[11px]"></i>
                                {{ item.address_summary }}
                            </p>
                        </div>

                        <!-- Pedido (Folio) -->
                        <div class="md:col-span-3 flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                {{ item.order_folio }}
                            </span>
                            <span class="text-xs text-slate-400 font-medium">· {{ item.delivery_zone }}</span>
                        </div>

                        <!-- Fijos listos -->
                        <div class="md:col-span-2 flex items-center justify-between md:justify-center gap-2 bg-blue-50/40 md:bg-transparent p-2 md:p-0 rounded-xl">
                            <span class="text-xs font-bold text-slate-500 md:hidden">Fijos listos:</span>
                            <div class="text-center">
                                <span class="text-base sm:text-lg font-black text-slate-900 font-mono">
                                    {{ item.ready_quantity }}
                                </span>
                                <span class="text-xs text-slate-500 font-semibold ml-1">listos</span>
                            </div>
                        </div>

                        <!-- Acciones: Aceptar carga -->
                        <div class="md:col-span-3 flex items-center justify-end gap-2 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                            <button
                                @click="openLoadModal(item)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-95 transition shadow-2xs min-h-[44px]"
                            >
                                <i class="fa-solid fa-check text-sky-400 text-xs"></i>
                                <span>Aceptar carga</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: ACEPTAR CARGA INDIVIDUAL (TOTAL O PARCIAL)                        -->
        <!-- ========================================================================= -->
        <div
            v-if="showLoadModal && itemToLoad"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold text-blue-900 uppercase tracking-wider block">Carga física al camión</span>
                        <h3 class="text-base font-black text-slate-900">
                            {{ itemToLoad.customer.nickname ? `"${itemToLoad.customer.nickname}"` : itemToLoad.customer.name }}
                        </h3>
                        <p class="text-xs text-slate-500">Folio: {{ itemToLoad.order_folio }} · Listos en Planta: {{ itemToLoad.ready_quantity }} fijos</p>
                    </div>
                    <button
                        @click="closeLoadModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Selector de cantidad cargada -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-2">
                        <span class="text-xs font-bold text-slate-600 block">Cantidad subida físicamente al camión</span>
                        <div class="flex items-center justify-center gap-4">
                            <button
                                @click="decrementLoadQty"
                                :disabled="loadForm.quantity <= 1"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <span class="text-3xl font-black text-slate-900 font-mono w-16 text-center">
                                {{ loadForm.quantity }}
                            </span>
                            <button
                                @click="incrementLoadQty"
                                :disabled="loadForm.quantity >= itemToLoad.ready_quantity"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <p v-if="loadForm.quantity < itemToLoad.ready_quantity" class="text-[11px] text-amber-700 font-bold bg-amber-50 p-1.5 rounded-lg border border-amber-200">
                            Se cargará {{ loadForm.quantity }} y {{ itemToLoad.ready_quantity - loadForm.quantity }} botellón(es) permanecerán en Planta bajo custodia.
                        </p>
                    </div>

                    <!-- Si es parcial, motivo de diferencia -->
                    <div v-if="loadForm.quantity < itemToLoad.ready_quantity" class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-700 block">Motivo de diferencia (opcional):</label>
                        <input
                            v-model="loadForm.difference_reason"
                            type="text"
                            placeholder="Ej. Espacio insuficiente en camión, se lleva en la tarde..."
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900"
                        />
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    * Únicamente las unidades aceptadas pasarán a <strong>Por entregar</strong> asociadas a este cliente.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeLoadModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px]"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitSingleLoad"
                        :disabled="loadForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px]"
                    >
                        {{ loadForm.processing ? 'Cargando...' : `Confirmar carga de ${loadForm.quantity} fijos` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 2: ACEPTAR TODOS EN CARGA                                           -->
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
                        <h3 class="text-base font-black text-slate-900">Aceptar toda la carga lista</h3>
                        <p class="text-xs text-slate-500">Confirmación masiva de carga al camión</p>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Pedidos listos:</span>
                        <span class="font-black text-slate-900 font-mono text-sm">{{ totalOrders }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-600">Total de botellones fijos:</span>
                        <span class="font-black text-blue-900 font-mono text-base">{{ totalBottles }} unidades (20 L)</span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    * Confirma que todas las unidades han sido cotejadas físicamente y subidas al camión.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeAcceptAllModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px]"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitAcceptAll"
                        :disabled="acceptAllForm.processing || totalOrders === 0"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white text-xs font-black min-h-[44px]"
                    >
                        {{ acceptAllForm.processing ? 'Cargando...' : 'Confirmar carga masiva' }}
                    </button>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

