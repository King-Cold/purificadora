<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activeRoute: {
        type: Object,
        nullable: true,
    },
    truck: {
        type: Object,
        required: true,
    },
    unit_price: {
        type: Number,
        default: 15.00,
    },
    max_pending_payment_bottles: {
        type: Number,
        default: 0,
    },
    initialCustomer: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.direct || null);

// -------------------------------------------------------------------------
// MODO CLIENTE: EXISTENTE VS NUEVO
// -------------------------------------------------------------------------
const clientMode = ref(props.initialCustomer ? 'search' : 'search'); // 'search' o 'new'

// Búsqueda de cliente existente
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedCustomer = ref(props.initialCustomer || null);

let searchTimeout = null;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    if (!searchQuery.value || searchQuery.value.trim().length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(route('repartidor.customers.search', { q: searchQuery.value }));
            if (res.ok) {
                searchResults.value = await res.json();
            }
        } catch (e) {
            console.error('Error buscando clientes:', e);
        } finally {
            isSearching.value = false;
        }
    }, 300);
};

const selectCustomer = (cust) => {
    selectedCustomer.value = cust;
    searchQuery.value = cust.nickname;
    searchResults.value = [];
};

const clearSelectedCustomer = () => {
    selectedCustomer.value = null;
    searchQuery.value = '';
    searchResults.value = [];
};

// Formulario de nuevo cliente rápido
const newCustomer = ref({
    nickname: '',
    name: '',
    phone: '',
    street: '',
    exterior_number: '',
    neighborhood: '',
    delivery_zone: '',
    reference: '',
});

// -------------------------------------------------------------------------
// CANTIDADES DE OPERACIÓN
// -------------------------------------------------------------------------
const exchangeQty = ref(1);
const exchangeEmptyReceived = ref(1);
const fixedQty = ref(0);

const maxExchangeAvailable = computed(() => {
    return Math.min(props.truck.exchange_full_available, exchangeEmptyReceived.value);
});

const exchangeDelivered = computed(() => {
    return Math.min(exchangeQty.value, maxExchangeAvailable.value);
});

const totalDeliveredBottles = computed(() => exchangeDelivered.value);

// Validación de pedido activo
const hasActiveOrder = computed(() => {
    return clientMode.value === 'search' && !!selectedCustomer.value?.active_order;
});

// Promoción semanal 9+1
const promoApplied = computed(() => {
    if (totalDeliveredBottles.value <= 0) return false;
    const promo = selectedCustomer.value?.weekly_promo;
    if (promo?.can_redeem) return true;
    const paidInWeek = promo?.paid_count || 0;
    const paidNow = unitsPaidCash.value || 0;
    if (promo && (paidInWeek + paidNow >= promo.required) && (totalDeliveredBottles.value - paidNow) > 0) {
        return true;
    }
    return false;
});

const unitsToCharge = computed(() => {
    const total = totalDeliveredBottles.value;
    if (total <= 0) return 0;
    return promoApplied.value ? Math.max(0, total - 1) : total;
});

// Cobro
const unitsPaidCash = ref(unitsToCharge.value);
watch(unitsToCharge, (newVal) => {
    unitsPaidCash.value = newVal;
});

const unitsToCredit = computed(() => {
    return Math.max(0, unitsToCharge.value - unitsPaidCash.value);
});

const currentDebtBottles = computed(() => {
    return selectedCustomer.value?.traffic_light?.pending_bottles || 0;
});

const creditLimit = computed(() => {
    if (clientMode.value === 'new') {
        return Number(props.max_pending_payment_bottles ?? 0);
    }
    return Number(selectedCustomer.value?.traffic_light?.limit ?? props.max_pending_payment_bottles ?? 0);
});

const availableCreditMargin = computed(() => {
    return Math.max(0, creditLimit.value - currentDebtBottles.value);
});

const isCreditExceeded = computed(() => {
    if (clientMode.value === 'new') {
        // Cliente nuevo empieza con 0 deuda, límite configurable
        return unitsToCredit.value > creditLimit.value;
    }
    if (unitsToCredit.value <= 0) return false;
    return unitsToCredit.value > availableCreditMargin.value;
});

const cashAmountToPay = computed(() => {
    return Math.round(unitsPaidCash.value * props.unit_price * 100) / 100;
});

const cashTendered = ref(cashAmountToPay.value);
watch(cashAmountToPay, (newVal) => {
    cashTendered.value = newVal;
});

const changeReturned = computed(() => {
    const diff = Number(cashTendered.value || 0) - cashAmountToPay.value;
    return diff > 0 ? Math.round(diff * 100) / 100 : 0;
});

const setQuickCash = (val) => {
    if (val === 'exact') {
        cashTendered.value = cashAmountToPay.value;
    } else {
        cashTendered.value = val;
    }
};

// Formulario de Envío
const form = useForm({
    customer_id: null,
    nickname: '',
    name: '',
    phone: '',
    street: '',
    exterior_number: '',
    neighborhood: '',
    delivery_zone: '',
    reference: '',
    exchange_quantity: 1,
    exchange_empty_received: 1,
    fixed_quantity: 0,
    units_paid_cash: 1,
    cash_tendered: 15,
});

const submitDirectOrder = () => {
    if (clientMode.value === 'search' && !selectedCustomer.value) {
        alert('Debes buscar y seleccionar un cliente o cambiar a la pestaña "Nuevo Cliente".');
        return;
    }

    if (hasActiveOrder.value) {
        alert('Este cliente ya cuenta con un pedido activo. No se puede registrar otro pedido hasta que se complete o cancele.');
        return;
    }

    if (clientMode.value === 'new' && !newCustomer.value.nickname.trim()) {
        alert('El apodo es obligatorio para el nuevo cliente.');
        return;
    }

    if (isCreditExceeded.value) {
        alert(`No es posible fiar ${unitsToCredit.value} botellones.`);
        return;
    }

    if (unitsPaidCash.value > 0 && Number(cashTendered.value || 0) < cashAmountToPay.value) {
        alert(`El efectivo recibido ($${cashTendered.value}) es insuficiente para cubrir los $${cashAmountToPay.value}.`);
        return;
    }

    if (clientMode.value === 'search') {
        form.customer_id = selectedCustomer.value.id;
    } else {
        form.customer_id = null;
        form.nickname = newCustomer.value.nickname.trim();
        form.name = newCustomer.value.name ? newCustomer.value.name.trim() : null;
        form.phone = newCustomer.value.phone ? newCustomer.value.phone.trim() : null;
        form.street = newCustomer.value.street ? newCustomer.value.street.trim() : null;
        form.exterior_number = newCustomer.value.exterior_number ? newCustomer.value.exterior_number.trim() : null;
        form.neighborhood = newCustomer.value.neighborhood ? newCustomer.value.neighborhood.trim() : null;
        form.delivery_zone = newCustomer.value.delivery_zone || null;
        form.reference = newCustomer.value.reference ? newCustomer.value.reference.trim() : null;
    }

    form.exchange_quantity = exchangeQty.value;
    form.exchange_empty_received = exchangeEmptyReceived.value;
    form.fixed_quantity = fixedQty.value;
    form.units_paid_cash = unitsPaidCash.value;
    form.cash_tendered = cashTendered.value;

    form.post(route('repartidor.direct-service.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Atender Cliente al Paso · Repartidor" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto space-y-4 pb-16">

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO Y REGRESO                                                    -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="route('repartidor.route.index')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-50 active:scale-95 transition"
                >
                    <span>⬅️</span> Volver a Mi Ruta
                </Link>

                <div class="text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Servicio en Ruta</span>
                    <span class="text-xs font-extrabold text-blue-900">Cliente al Paso</span>
                </div>
            </div>

            <!-- Alertas de error -->
            <div v-if="flashError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold">
                {{ flashError }}
            </div>

            <!-- Existencias en Camión -->
            <div class="p-3 rounded-2xl bg-blue-50/80 border border-blue-200/70 text-xs flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-truck text-lg text-blue-900"></i>
                    <span class="font-bold text-blue-950">Existencias en tu camión:</span>
                </div>
                <div class="text-right font-extrabold text-blue-900">
                    <span>{{ truck.exchange_full_available }} llenos</span> /
                    <span class="text-slate-500">{{ truck.exchange_empty_collected }} vacíos</span>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. SELECTOR DE CLIENTE (BUSCAR O NUEVO)                                    -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <button
                        @click="clientMode = 'search'"
                        type="button"
                        class="flex-1 py-2 text-xs sm:text-sm font-black rounded-xl transition text-center inline-flex items-center justify-center gap-1.5"
                        :class="clientMode === 'search' ? 'bg-blue-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i> Buscar Cliente
                    </button>
                    <button
                        @click="clientMode = 'new'"
                        type="button"
                        class="flex-1 py-2 text-xs sm:text-sm font-black rounded-xl transition text-center inline-flex items-center justify-center gap-1.5"
                        :class="clientMode === 'new' ? 'bg-blue-900 text-white shadow-2xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    >
                        <i class="fa-solid fa-user-plus"></i> Nuevo Cliente Rápido
                    </button>
                </div>

                <!-- MODO A: BUSCADOR -->
                <div v-if="clientMode === 'search'" class="space-y-3">
                    <div v-if="!selectedCustomer" class="space-y-2">
                        <label class="block text-xs font-extrabold text-slate-700">
                            Buscar por apodo, nombre, código o teléfono:
                        </label>
                        <input
                            v-model="searchQuery"
                            @input="onSearchInput"
                            type="text"
                            placeholder="Ej. Don Pancho, CLI-001..."
                            class="w-full text-sm py-2.5 px-3 rounded-xl border border-slate-200 bg-white"
                        />

                        <!-- Resultados -->
                        <div v-if="isSearching" class="text-xs text-slate-400 py-2 text-center">Buscando...</div>
                        <div v-else-if="searchResults.length > 0" class="divide-y divide-slate-100 border border-slate-100 rounded-xl max-h-48 overflow-y-auto">
                            <button
                                v-for="c in searchResults"
                                :key="c.id"
                                @click="selectCustomer(c)"
                                type="button"
                                class="w-full p-2.5 text-left hover:bg-blue-50/50 flex items-center justify-between text-xs transition"
                            >
                                <div>
                                    <span class="font-bold text-slate-900">{{ c.nickname }}</span>
                                    <span v-if="c.name" class="text-slate-500 block text-[11px]">{{ c.name }}</span>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400">{{ c.code }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cliente seleccionado -->
                    <div v-else class="space-y-3">
                        <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 text-xs flex items-center justify-between gap-3">
                            <div>
                                <span class="font-mono text-[10px] text-emerald-700 font-bold block">{{ selectedCustomer.code }}</span>
                                <span class="text-base font-black text-emerald-950 block">{{ selectedCustomer.nickname }}</span>
                                <span v-if="selectedCustomer.name" class="text-slate-600 text-[11px]">{{ selectedCustomer.name }}</span>
                            </div>
                            <button
                                @click="clearSelectedCustomer"
                                type="button"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-bold text-rose-700 bg-white border border-rose-200 hover:bg-rose-50"
                            >
                                Cambiar
                            </button>
                        </div>

                        <!-- ALERTA SI TIENE PEDIDO ACTIVO -->
                        <div
                            v-if="selectedCustomer.active_order"
                            class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs space-y-2"
                        >
                            <div class="flex items-center gap-2 font-black text-amber-900">
                                <i class="fa-solid fa-triangle-exclamation text-amber-700"></i>
                                <span>El cliente ya cuenta con un pedido activo</span>
                            </div>
                            <p class="text-amber-800 leading-relaxed">
                                Pedido <strong>{{ selectedCustomer.active_order.folio }}</strong> en estado <strong>{{ selectedCustomer.active_order.status_label }}</strong>.
                                Por política del sistema, cada cliente tiene como máximo un pedido activo a la vez.
                            </p>
                            <div class="pt-1">
                                <a
                                    :href="route('orders.show', selectedCustomer.active_order.id)"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-600 text-white font-bold text-xs hover:bg-amber-700 transition"
                                >
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Consultar Pedido Activo
                                </a>
                            </div>
                        </div>

                        <!-- Promoción semanal del cliente si aplica -->
                        <div
                            v-if="selectedCustomer.weekly_promo"
                            class="p-2.5 rounded-xl bg-blue-50 border border-blue-200 text-xs flex items-center justify-between text-blue-900"
                        >
                            <span class="flex items-center gap-1.5"><i class="fa-solid fa-gift text-blue-700"></i> Promoción semanal:</span>
                            <span class="font-bold">{{ selectedCustomer.weekly_promo.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- MODO B: ALTA RÁPIDA -->
                <div v-else class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Apodo del cliente <span class="text-rose-500">*</span>:</label>
                        <input
                            v-model="newCustomer.nickname"
                            type="text"
                            placeholder="Ej. Tienda Doña Mary, Carnicería el Toro..."
                            class="w-full text-sm py-2 px-3 rounded-xl border border-slate-200 bg-white"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nombre (opcional):</label>
                            <input
                                v-model="newCustomer.name"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full text-xs py-2 px-3 rounded-xl border border-slate-200 bg-white"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Teléfono (opcional):</label>
                            <input
                                v-model="newCustomer.phone"
                                type="text"
                                placeholder="10 dígitos"
                                class="w-full text-xs py-2 px-3 rounded-xl border border-slate-200 bg-white"
                            />
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 italic">
                        * No se exige cuenta de correo, contraseña ni domicilio para registrar al cliente en el momento.
                    </p>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. OPERACIÓN DE BOTELLONES                                                -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4">
                <h3 class="font-extrabold text-blue-950 text-sm sm:text-base border-b border-slate-100 pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-bottle-water text-blue-900"></i> Cantidades del Servicio
                </h3>

                <!-- Botellones de Cambio -->
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Vacíos que entrega el cliente:</label>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="exchangeEmptyReceived = Math.max(0, exchangeEmptyReceived - 1)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                                >-</button>
                                <input
                                    v-model.number="exchangeEmptyReceived"
                                    type="number"
                                    min="0"
                                    class="w-full text-center font-black py-2 rounded-xl border border-slate-200"
                                />
                                <button
                                    @click="exchangeEmptyReceived = exchangeEmptyReceived + 1"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                                >+</button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Llenos que solicita:</label>
                            <div class="flex items-center gap-2">
                                <button
                                    @click="exchangeQty = Math.max(0, exchangeQty - 1)"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                                >-</button>
                                <input
                                    v-model.number="exchangeQty"
                                    type="number"
                                    min="0"
                                    class="w-full text-center font-black py-2 rounded-xl border border-slate-200 text-blue-950"
                                />
                                <button
                                    @click="exchangeQty = exchangeQty + 1"
                                    type="button"
                                    class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                                >+</button>
                            </div>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-slate-50 text-xs flex justify-between text-slate-600">
                        <span>Llenos efectivamente entregados:</span>
                        <span class="font-black text-blue-950 text-sm">{{ exchangeDelivered }} garrafones</span>
                    </div>
                </div>

                <!-- Botellones Fijos para recoger -->
                <div class="pt-3 border-t border-slate-100 space-y-2">
                    <label class="block text-xs font-bold text-purple-900">
                        Botellones fijos a recoger (Llevando a planta):
                    </label>
                    <div class="flex items-center gap-2">
                        <button
                            @click="fixedQty = Math.max(0, fixedQty - 1)"
                            type="button"
                            class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                        >-</button>
                        <input
                            v-model.number="fixedQty"
                            type="number"
                            min="0"
                            class="w-24 text-center font-black py-2 rounded-xl border border-slate-200 text-purple-950"
                        />
                        <button
                            @click="fixedQty = fixedQty + 1"
                            type="button"
                            class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                        >+</button>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 4. COBRO DE CONTADO Y FIADO                                               -->
            <!-- ========================================================================= -->
            <div
                v-if="totalDeliveredBottles > 0"
                class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
                        <i class="fa-solid fa-cash-register text-slate-800"></i> Cobro en Ruta
                    </h3>
                    <span class="text-xs font-mono font-bold text-slate-500">${{ unit_price }} c/u</span>
                </div>

                <!-- Unidades de contado -->
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <label class="font-extrabold text-slate-800">¿Cuántos paga de contado?</label>
                        <span class="font-black text-blue-900">{{ unitsPaidCash }} de {{ totalDeliveredBottles }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="unitsPaidCash = Math.max(0, unitsPaidCash - 1)"
                            type="button"
                            class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                        >-</button>
                        <input
                            v-model.number="unitsPaidCash"
                            type="number"
                            min="0"
                            :max="totalDeliveredBottles"
                            class="w-full text-center font-black py-2 rounded-xl border border-slate-200 text-emerald-950"
                        />
                        <button
                            @click="unitsPaidCash = Math.min(totalDeliveredBottles, unitsPaidCash + 1)"
                            type="button"
                            class="w-10 h-10 rounded-xl bg-slate-100 font-bold text-lg active:scale-95"
                        >+</button>
                    </div>

                    <div v-if="unitsToCredit > 0" class="p-2.5 rounded-xl text-xs" :class="isCreditExceeded ? 'bg-rose-100 text-rose-900 font-bold border border-rose-200' : 'bg-amber-50 text-amber-900 font-bold'">
                        <template v-if="creditLimit === 0">
                            Fiado deshabilitado (Límite: 0). No se pueden fiar {{ unitsToCredit }} botellones.
                        </template>
                        <template v-else-if="isCreditExceeded">
                            Límite excedido. Solo puedes fiar hasta {{ availableCreditMargin }} botellones.
                        </template>
                        <template v-else>
                            Botellones fiados: {{ unitsToCredit }} (Margen disponible: {{ availableCreditMargin }})
                        </template>
                    </div>
                </div>

                <!-- Efectivo recibido -->
                <div v-if="unitsPaidCash > 0" class="space-y-3 pt-2 border-t border-slate-100 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-600">Total a cobrar:</span>
                        <span class="text-2xl font-black text-emerald-950">${{ cashAmountToPay }}</span>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Efectivo recibido ($):</label>
                        <input
                            v-model.number="cashTendered"
                            type="number"
                            step="0.5"
                            class="w-full text-xl font-black py-2 px-3 rounded-xl border border-slate-200 bg-white"
                        />
                    </div>

                    <!-- Botones rápidos -->
                    <div class="flex gap-2 flex-wrap">
                        <button
                            @click="setQuickCash('exact')"
                            type="button"
                            class="px-3 py-1.5 rounded-xl font-bold bg-slate-100 hover:bg-slate-200 active:scale-95"
                        >Exacto</button>
                        <button
                            v-for="amt in [50, 100, 200]"
                            :key="amt"
                            v-show="amt >= cashAmountToPay"
                            @click="setQuickCash(amt)"
                            type="button"
                            class="px-3 py-1.5 rounded-xl font-bold bg-slate-100 hover:bg-slate-200 active:scale-95"
                        >${{ amt }}</button>
                    </div>

                    <div v-if="changeReturned > 0" class="p-2.5 rounded-xl bg-emerald-50 text-emerald-900 font-black text-xs">
                        Cambio: ${{ changeReturned }}
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 5. CONFIRMAR                                                              -->
            <!-- ========================================================================= -->
            <button
                @click="submitDirectOrder"
                :disabled="form.processing || hasActiveOrder || isCreditExceeded || (unitsPaidCash > 0 && cashTendered < cashAmountToPay)"
                type="button"
                class="w-full py-4 rounded-2xl text-base font-black bg-blue-600 text-white hover:bg-blue-700 active:scale-98 transition shadow-lg flex items-center justify-center gap-2 disabled:opacity-50"
            >
                <span v-if="hasActiveOrder" class="inline-flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation"></i> Cliente ya cuenta con pedido activo</span>
                <span v-else class="inline-flex items-center gap-1.5"><i class="fa-solid fa-check"></i> Confirmar Servicio Directo en Ruta</span>
            </button>

        </div>
    </AuthenticatedLayout>
</template>

