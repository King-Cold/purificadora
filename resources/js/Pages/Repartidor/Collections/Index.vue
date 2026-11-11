<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    orders: {
        type: Array,
        default: () => [],
    },
    zoneCounts: {
        type: Object,
        default: () => ({ all: 0, north: 0, south: 0, east: 0, west: 0, none: 0 }),
    },
    activeRoute: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.collect || page.props.errors?.cancel || page.props.errors?.quantity || null);

// Filtro de Zona
const selectedZone = ref('all');

const zoneTabs = [
    { key: 'all', label: 'Todas' },
    { key: 'north', label: 'Norte' },
    { key: 'south', label: 'Sur' },
    { key: 'east', label: 'Este' },
    { key: 'west', label: 'Oeste' },
    { key: 'none', label: 'Sin zona' },
];

const filteredOrders = computed(() => {
    if (selectedZone.value === 'all') {
        return props.orders;
    }
    return props.orders.filter(o => o.delivery_zone_key === selectedZone.value);
});

// Modal 1: Detalle del Cliente/Pedido
const showDetailModal = ref(false);
const selectedOrder = ref(null);

const openDetailModal = (order) => {
    selectedOrder.value = order;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedOrder.value = null;
};

// Modal 2: Confirmar Recogida (Modo Normal o Modo Pago Anticipado)
const showCollectModal = ref(false);
const isAdvancePaymentMode = ref(false);
const orderToCollect = ref(null);
const unitPrice = ref(15);

const collectForm = useForm({
    quantity: 1,
    is_advance_payment: false,
    cash_tendered: 0,
    notes: '',
});

const openCollectModal = (order, advance = false) => {
    orderToCollect.value = order;
    isAdvancePaymentMode.value = advance;
    const initialQty = order.fixed_quantity_pending || 1;
    collectForm.quantity = initialQty;
    collectForm.is_advance_payment = advance;
    collectForm.cash_tendered = initialQty * unitPrice.value;
    collectForm.notes = '';
    collectForm.clearErrors();
    showCollectModal.value = true;
};

const incrementQuantity = () => {
    const max = orderToCollect.value?.fixed_quantity_pending || 99;
    if (collectForm.quantity < max) {
        collectForm.quantity++;
        collectForm.cash_tendered = collectForm.quantity * unitPrice.value;
    }
};

const decrementQuantity = () => {
    if (collectForm.quantity > 1) {
        collectForm.quantity--;
        collectForm.cash_tendered = collectForm.quantity * unitPrice.value;
    }
};

const closeCollectModal = () => {
    showCollectModal.value = false;
    orderToCollect.value = null;
};

const submitCollection = () => {
    if (!orderToCollect.value || collectForm.processing) return;

    collectForm.post(route('repartidor.orders.collect-fixed', orderToCollect.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCollectModal(),
    });
};

// Modal 3: Cancelar Pedido
const showCancelModal = ref(false);
const orderToCancel = ref(null);
const cancelForm = useForm({
    reason: '',
});

const openCancelModal = (order) => {
    orderToCancel.value = order;
    cancelForm.reason = '';
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    showCancelModal.value = false;
    orderToCancel.value = null;
};

const submitCancelOrder = () => {
    if (!orderToCancel.value || cancelForm.processing) return;

    cancelForm.post(route('repartidor.orders.cancel', orderToCancel.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCancelModal(),
    });
};
</script>

<template>
    <Head title="Por recoger · Botellones fijos de 20 L" />

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
            <!-- 1. ENCABEZADO Y FILTROS DE ZONA                                            -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-4 sm:p-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">
                            <Link :href="route('repartidor.reparto')" class="text-blue-900 hover:underline">Reparto</Link>
                            <span>/</span>
                            <span>Fijos 20 L</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                            <i class="fa-solid fa-hand-holding-hand text-blue-900"></i> Por recoger
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                            Botellones fijos vacíos en domicilio de clientes listos para recolección física y traslado a Planta.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 self-start md:self-center">
                        <span class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-900 border border-blue-200 text-xs font-black">
                            {{ filteredOrders.length }} pedido(s)
                        </span>
                        <Link
                            :href="route('repartidor.ready-to-load.index')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-dolly text-slate-500"></i> Listos para cargar →
                        </Link>
                    </div>
                </div>

                <!-- Filtro por Zona -->
                <div class="pt-3.5 flex items-center gap-2 overflow-x-auto">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 hidden sm:inline">Zona:</span>
                    <button
                        v-for="tab in zoneTabs"
                        :key="tab.key"
                        @click="selectedZone = tab.key"
                        type="button"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition shrink-0 inline-flex items-center gap-1.5 border min-h-[44px]"
                        :class="selectedZone === tab.key
                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="text-[10px] px-1.5 py-0.5 rounded-full font-black"
                            :class="selectedZone === tab.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ zoneCounts[tab.key] || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. TABLA COMPACTA: CLIENTE, CANTIDAD Y ACCIONES                            -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div v-if="filteredOrders.length === 0" class="p-12 text-center space-y-2">
                    <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center text-xl">
                        <i class="fa-solid fa-inbox"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-800">No hay fijos pendientes por recoger en esta zona</p>
                    <p class="text-xs text-slate-400">Los nuevos pedidos de fijos aparecerán aquí automáticamente.</p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <!-- Encabezado de la tabla (visible en escritorio y tablet horizontal) -->
                    <div class="hidden md:grid md:grid-cols-12 gap-3 px-5 py-3 bg-slate-50/80 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <div class="col-span-5">Cliente / Detalle</div>
                        <div class="col-span-2 text-center">Cantidad (20 L)</div>
                        <div class="col-span-5 text-right">Acciones</div>
                    </div>

                    <!-- Filas compactas -->
                    <div
                        v-for="order in filteredOrders"
                        :key="order.id"
                        class="p-4 sm:px-5 hover:bg-slate-50/60 transition group flex flex-col md:grid md:grid-cols-12 gap-3 md:items-center"
                    >
                        <!-- Columna 1: Cliente -->
                        <div class="md:col-span-5 space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <button
                                    @click="openDetailModal(order)"
                                    type="button"
                                    class="text-left font-black text-slate-900 hover:text-blue-900 transition text-sm sm:text-base underline decoration-slate-300 hover:decoration-blue-900"
                                    title="Ver dirección y teléfono"
                                >
                                    {{ order.customer.nickname ? `"${order.customer.nickname}"` : (order.customer.name || 'Cliente sin nombre') }}
                                </button>

                                <span v-if="order.customer.nickname && order.customer.name" class="text-xs text-slate-500 hidden sm:inline">
                                    ({{ order.customer.name }})
                                </span>

                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ order.folio }}
                                </span>
                            </div>

                            <p class="text-xs text-slate-500 line-clamp-1">
                                <i class="fa-solid fa-location-dot text-slate-400 mr-1 text-[11px]"></i>
                                {{ order.address_summary }}
                            </p>
                        </div>

                        <!-- Columna 2: Cantidad -->
                        <div class="md:col-span-2 flex items-center justify-between md:justify-center gap-2 bg-blue-50/40 md:bg-transparent p-2 md:p-0 rounded-xl">
                            <span class="text-xs font-bold text-slate-500 md:hidden">Por recoger:</span>
                            <div class="text-center">
                                <span class="text-base sm:text-lg font-black text-slate-900 font-mono">
                                    {{ order.fixed_quantity_pending }}
                                </span>
                                <span class="text-xs text-slate-500 font-semibold ml-1">fijos 20L</span>
                            </div>
                        </div>

                        <!-- Columna 3: Acciones (Confirmar recogida, Pago anticipado, Cancelar) -->
                        <div class="md:col-span-5 flex items-center justify-end gap-2 flex-wrap pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                            <!-- Acción 1: Confirmar recogida -->
                            <button
                                @click="openCollectModal(order, false)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white active:scale-95 transition shadow-2xs min-h-[44px]"
                            >
                                <i class="fa-solid fa-check text-xs"></i>
                                <span>Confirmar recogida</span>
                            </button>

                            <!-- Acción 2: Pago anticipado -->
                            <button
                                @click="openCollectModal(order, true)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-black bg-amber-500 hover:bg-amber-600 text-white active:scale-95 transition shadow-2xs min-h-[44px]"
                                title="Confirmar recogida y registrar pago inmediato"
                            >
                                <i class="fa-solid fa-hand-holding-dollar text-xs"></i>
                                <span>Pago anticipado</span>
                            </button>

                            <!-- Acción 3: Cancelar -->
                            <button
                                @click="openCancelModal(order)"
                                type="button"
                                class="inline-flex items-center justify-center gap-1 px-2.5 py-2 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 active:scale-95 transition min-h-[44px]"
                                title="Cancelar pedido"
                            >
                                <i class="fa-solid fa-xmark text-xs"></i>
                                <span>Cancelar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 1: DETALLE DEL PEDIDO Y CLIENTE                                     -->
        <!-- ========================================================================= -->
        <div
            v-if="showDetailModal && selectedOrder"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-mono uppercase tracking-wider text-slate-400 block">Detalle de cliente</span>
                        <h3 class="text-lg font-black text-slate-900">
                            {{ selectedOrder.customer.nickname ? `"${selectedOrder.customer.nickname}"` : selectedOrder.customer.name }}
                        </h3>
                        <p v-if="selectedOrder.customer.nickname && selectedOrder.customer.name" class="text-xs text-slate-500">
                            {{ selectedOrder.customer.name }}
                        </p>
                    </div>
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-700">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Domicilio de atención</span>
                        <p class="text-xs font-bold text-slate-800 leading-relaxed">{{ selectedOrder.address_summary }}</p>
                        <p class="text-[11px] text-blue-900 font-bold">Zona: {{ selectedOrder.delivery_zone }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase block">Folio</span>
                            <span class="text-xs font-mono font-black text-slate-900">{{ selectedOrder.folio }}</span>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase block">Código</span>
                            <span class="text-xs font-mono font-black text-slate-900">{{ selectedOrder.customer.code }}</span>
                        </div>
                    </div>

                    <div v-if="selectedOrder.customer.phone" class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 uppercase block">Teléfono</span>
                            <span class="text-xs font-bold text-emerald-950">{{ selectedOrder.customer.phone }}</span>
                        </div>
                        <a
                            :href="`tel:${selectedOrder.customer.phone}`"
                            class="h-10 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs inline-flex items-center gap-1.5 shadow-2xs"
                        >
                            <i class="fa-solid fa-phone"></i> Llamar
                        </a>
                    </div>
                </div>

                <div class="pt-2">
                    <button
                        @click="closeDetailModal"
                        type="button"
                        class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold min-h-[44px]"
                    >
                        Cerrar detalle
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 2: CONFIRMAR RECOGIDA / PAGO ANTICIPADO                              -->
        <!-- ========================================================================= -->
        <div
            v-if="showCollectModal && orderToCollect"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider block" :class="isAdvancePaymentMode ? 'text-amber-600' : 'text-blue-900'">
                            {{ isAdvancePaymentMode ? 'Recogida con pago anticipado' : 'Confirmar recogida de envases fijos' }}
                        </span>
                        <h3 class="text-lg font-black text-slate-900">
                            {{ orderToCollect.customer.nickname ? `"${orderToCollect.customer.nickname}"` : orderToCollect.customer.name }}
                        </h3>
                        <p class="text-xs text-slate-500">Folio: {{ orderToCollect.folio }} · Botellón 20 Litros</p>
                    </div>
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm"
                    >
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Selector de cantidad física recibida -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center space-y-2">
                        <span class="text-xs font-bold text-slate-600 block">Botellones vacíos recibidos físicamente</span>
                        <div class="flex items-center justify-center gap-4">
                            <button
                                @click="decrementQuantity"
                                :disabled="collectForm.quantity <= 1"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <span class="text-3xl font-black text-slate-900 font-mono w-16 text-center">
                                {{ collectForm.quantity }}
                            </span>
                            <button
                                @click="incrementQuantity"
                                :disabled="collectForm.quantity >= orderToCollect.fixed_quantity_pending"
                                type="button"
                                class="h-12 w-12 rounded-2xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 disabled:opacity-40 flex items-center justify-center text-lg font-bold shadow-2xs"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <p v-if="collectForm.quantity < orderToCollect.fixed_quantity_pending" class="text-[11px] text-amber-700 font-bold bg-amber-50 p-1.5 rounded-lg border border-amber-200">
                            Se registrará {{ collectForm.quantity }} y quedará 1 unidad pendiente de recolección identificada.
                        </p>
                    </div>

                    <!-- Si es modo pago anticipado, mostrar cálculo del cobro -->
                    <div v-if="isAdvancePaymentMode" class="p-4 bg-amber-50 rounded-2xl border border-amber-200 space-y-2">
                        <div class="flex items-center justify-between text-xs text-amber-900">
                            <span>Precio unitario a domicilio:</span>
                            <span class="font-bold">${{ unitPrice }} MXN</span>
                        </div>
                        <div class="flex items-center justify-between text-sm font-black text-amber-950 border-t border-amber-200 pt-2">
                            <span>Total a cobrar por anticipado:</span>
                            <span class="text-xl font-mono">${{ collectForm.quantity * unitPrice }} MXN</span>
                        </div>
                        <p class="text-[11px] text-amber-800 leading-tight">
                            * El cobro en efectivo se vinculará únicamente a las unidades efectivamente recibidas. No marcará el pedido como entregado.
                        </p>
                    </div>

                    <!-- Notas opcionales -->
                    <div class="space-y-1">
                        <label class="text-[11px] font-bold text-slate-600 block">Notas de recolección (opcional):</label>
                        <input
                            v-model="collectForm.notes"
                            type="text"
                            placeholder="Ej. Envase rotulado en buen estado..."
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-900 focus:ring-blue-900"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeCollectModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px]"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitCollection"
                        :disabled="collectForm.processing"
                        type="button"
                        class="px-5 py-2.5 rounded-xl text-xs font-black text-white active:scale-95 transition shadow-2xs min-h-[44px] inline-flex items-center gap-1.5"
                        :class="isAdvancePaymentMode ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-900 hover:bg-blue-800'"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>{{ collectForm.processing ? 'Guardando...' : (isAdvancePaymentMode ? 'Guardar recogida y cobro' : 'Confirmar recogida') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL 3: CANCELAR PEDIDO                                                  -->
        <!-- ========================================================================= -->
        <div
            v-if="showCancelModal && orderToCancel"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        >
            <div class="bg-white rounded-3xl max-w-md w-full p-5 sm:p-6 shadow-2xl border border-rose-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center gap-3 text-rose-600 border-b border-rose-100 pb-3">
                    <div class="h-10 w-10 rounded-2xl bg-rose-100 flex items-center justify-center text-lg shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">¿Seguro que quieres cancelar este pedido?</h3>
                        <p class="text-xs text-slate-500">Folio: {{ orderToCancel.folio }} · {{ orderToCancel.customer.nickname || orderToCancel.customer.name }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-700 block">
                        Motivo obligatorio de cancelación:
                    </label>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="3"
                        placeholder="Ej. El cliente indicó que no requiere el servicio el día de hoy..."
                        class="w-full text-xs rounded-xl border-slate-300 focus:border-rose-500 focus:ring-rose-500"
                    ></textarea>
                    <p class="text-[11px] text-slate-500">
                        * Si ya se recolectaron envases fijos, su custodia física y devolución se mantendrán registradas.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="closeCancelModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold min-h-[44px]"
                    >
                        Volver
                    </button>
                    <button
                        @click="submitCancelOrder"
                        :disabled="cancelForm.processing || cancelForm.reason.trim().length < 5"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-black active:scale-95 transition disabled:opacity-40 min-h-[44px]"
                    >
                        {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
                    </button>
                </div>
            </div>
        </div>

        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>
