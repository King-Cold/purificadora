<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    scheduleStatus: {
        type: Object,
        required: true,
    },
    scheduleSummary: {
        type: Object,
        required: true,
    },
    preselectedCustomer: {
        type: Object,
        default: null,
    },
});

// Estado de selección de cliente
const selectedCustomer = ref(props.preselectedCustomer || null);
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const searchDropdownOpen = ref(false);
let searchDebounceTimer = null;

// Formulario de creación de pedido
const form = useForm({
    customer_id: props.preselectedCustomer ? props.preselectedCustomer.id : null,
    fixed_quantity: 0,
    exchange_quantity: 0,
    notes: '',
});

// Búsqueda en tiempo real de clientes sin cuenta
const searchCustomers = async () => {
    if (!searchQuery.value || searchQuery.value.trim().length < 1) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await axios.get(route('admin.orders.search-customers'), {
            params: { search: searchQuery.value.trim() },
        });
        searchResults.value = response.data || [];
        searchDropdownOpen.value = true;
    } catch (err) {
        console.error('Error buscando clientes:', err);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        searchCustomers();
    }, 300);
};

const selectCustomer = (cust) => {
    selectedCustomer.value = cust;
    form.customer_id = cust.id;
    form.fixed_quantity = 0;
    form.exchange_quantity = 0;
    form.clearErrors();
    searchQuery.value = '';
    searchResults.value = [];
    searchDropdownOpen.value = false;
};

const clearCustomerSelection = () => {
    selectedCustomer.value = null;
    form.customer_id = null;
    form.fixed_quantity = 0;
    form.exchange_quantity = 0;
    form.clearErrors();
    searchQuery.value = '';
    searchResults.value = [];
};

// Inventario registrado del cliente seleccionado
const registeredFixed = computed(() => Number(selectedCustomer.value?.fixed_bottles_count ?? 0));
const registeredExchange = computed(() => Number(selectedCustomer.value?.exchange_bottles_count ?? 0));
const totalRegistered = computed(() => Number(selectedCustomer.value?.total_bottles ?? 0));

// Cantidad total seleccionada en el formulario
const totalSelected = computed(() => {
    return Number(form.fixed_quantity || 0) + Number(form.exchange_quantity || 0);
});

// Validaciones reactivas de negocio
const hasActiveOrder = computed(() => Boolean(selectedCustomer.value?.active_order));
const hasAddress = computed(() => Boolean(selectedCustomer.value?.has_address));
const hasNoBottles = computed(() => selectedCustomer.value && totalRegistered.value <= 0);
const isScheduleClosed = computed(() => !props.scheduleStatus?.can_receive);

const hasValidQuantity = computed(() => {
    return totalSelected.value > 0 &&
        Number(form.fixed_quantity || 0) <= registeredFixed.value &&
        Number(form.exchange_quantity || 0) <= registeredExchange.value;
});

const canSubmit = computed(() => {
    return selectedCustomer.value !== null &&
        !hasActiveOrder.value &&
        hasAddress.value &&
        !hasNoBottles.value &&
        !isScheduleClosed.value &&
        hasValidQuantity.value &&
        !form.processing;
});

// Controles para botellones fijos
const incrementFixed = () => {
    if (form.fixed_quantity < registeredFixed.value) {
        form.fixed_quantity++;
    }
};

const decrementFixed = () => {
    if (form.fixed_quantity > 0) {
        form.fixed_quantity--;
    }
};

const setMaxFixed = () => {
    form.fixed_quantity = registeredFixed.value;
};

// Controles para botellones de cambio
const incrementExchange = () => {
    if (form.exchange_quantity < registeredExchange.value) {
        form.exchange_quantity++;
    }
};

const decrementExchange = () => {
    if (form.exchange_quantity > 0) {
        form.exchange_quantity--;
    }
};

const setMaxExchange = () => {
    form.exchange_quantity = registeredExchange.value;
};

const submitOrder = () => {
    if (!canSubmit.value) return;

    form.post(route('admin.orders.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Registrar Pedido Administrativo - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <Link
                        :href="route('admin.orders.index')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0 cursor-pointer"
                        title="Volver a la lista de pedidos"
                    >
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                                Registrar Pedido
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900 border border-blue-200/80">
                                Clientes Sin Cuenta
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Alta administrativa de pedidos a domicilio para personas particulares
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border"
                        :class="scheduleStatus.can_receive ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                    >
                        <span class="h-2 w-2 rounded-full" :class="scheduleStatus.can_receive ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                        <span>{{ scheduleStatus.can_receive ? 'Horario de recepción activo' : 'Recepción cerrada' }}</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ========================================================================= -->
                <!-- 1. ALERTA GLOBAL DE HORARIO DE ATENCIÓN (SI ESTÁ CERRADO O EN DESCANSO) -->
                <!-- ========================================================================= -->
                <div
                    v-if="!scheduleStatus.can_receive"
                    class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-clock text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-amber-950">Horario de recepción no disponible</h4>
                        <p class="text-xs sm:text-sm text-amber-800 mt-0.5 leading-relaxed font-medium">
                            {{ scheduleStatus.message }}
                        </p>
                        <div v-if="scheduleSummary" class="mt-2 text-xs text-amber-700">
                            Horario habitual: {{ scheduleSummary.opening_time }} - {{ scheduleSummary.closing_time }} hrs.
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 2. SELECCIÓN DE CLIENTE SIN CUENTA -->
                <!-- ========================================================================= -->
                <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center text-sm font-bold">
                                1
                            </div>
                            <h3 class="text-base font-bold text-slate-900">
                                Cliente sin cuenta vinculada
                            </h3>
                        </div>
                        <span v-if="selectedCustomer" class="text-xs text-slate-500">
                            Código: <span class="font-mono font-semibold text-slate-800">{{ selectedCustomer.code }}</span>
                        </span>
                    </div>

                    <!-- A. BUSCADOR EN TIEMPO REAL (SI NO HAY CLIENTE SELECCIONADO) -->
                    <div v-if="!selectedCustomer" class="space-y-3 relative">
                        <label for="customer_search" class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Buscar cliente por nombre, apodo, código o teléfono
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i v-if="!isSearching" class="fa-solid fa-magnifying-glass text-sm"></i>
                                <i v-else class="fa-solid fa-spinner fa-spin text-sm text-blue-900"></i>
                            </div>
                            <input
                                id="customer_search"
                                v-model="searchQuery"
                                @input="onSearchInput"
                                @focus="searchDropdownOpen = true"
                                type="text"
                                placeholder="Escribe al menos un caracter para buscar (ej. Juan, HOC-0001, 9991234567)..."
                                class="w-full pl-10 pr-4 py-2.5 rounded-xl border-slate-200 text-sm focus:border-blue-900 focus:ring-blue-900 transition shadow-2xs placeholder:text-slate-400"
                                autocomplete="off"
                            />
                        </div>

                        <!-- Dropdown de resultados -->
                        <div
                            v-if="searchDropdownOpen && searchResults.length > 0"
                            class="absolute z-20 left-0 right-0 mt-1 bg-white rounded-xl shadow-lg border border-slate-200 max-h-72 overflow-y-auto divide-y divide-slate-100"
                        >
                            <button
                                v-for="cust in searchResults"
                                :key="cust.id"
                                type="button"
                                @click="selectCustomer(cust)"
                                class="w-full text-left p-3.5 hover:bg-blue-50/50 transition flex items-center justify-between gap-3 cursor-pointer"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-900 truncate">{{ cust.name }}</span>
                                        <span v-if="cust.nickname" class="text-xs px-2 py-0.5 bg-slate-100 text-slate-600 rounded-md">
                                            "{{ cust.nickname }}"
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-slate-500 mt-1">
                                        <span class="font-mono text-slate-700 font-semibold">{{ cust.code }}</span>
                                        <span v-if="cust.phone">&bull; {{ cust.phone }}</span>
                                    </div>
                                    <div v-if="cust.address" class="text-xs text-slate-500 truncate mt-0.5">
                                        <i class="fa-solid fa-location-dot text-slate-400 mr-1"></i>
                                        {{ cust.address.full_address }}
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="flex items-center gap-1.5 justify-end text-xs font-semibold">
                                        <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-900">
                                            Fijos: {{ cust.fixed_bottles_count }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-900">
                                            Cambio: {{ cust.exchange_bottles_count }}
                                        </span>
                                    </div>
                                    <span v-if="cust.active_order" class="inline-block mt-1 text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                        Tiene pedido activo
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div
                            v-else-if="searchDropdownOpen && searchQuery.trim().length > 0 && !isSearching"
                            class="p-4 text-center text-xs text-slate-500 bg-slate-50 rounded-xl border border-slate-100"
                        >
                            No se encontraron clientes sin cuenta con el término "{{ searchQuery }}".
                        </div>

                        <InputError :message="form.errors.customer_id" class="mt-2" />
                    </div>

                    <!-- B. TARJETA DE CLIENTE SELECCIONADO -->
                    <div v-else class="space-y-4">
                        <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-3.5">
                                <div class="h-11 w-11 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-base shrink-0 shadow-2xs">
                                    {{ selectedCustomer.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-base font-bold text-slate-900">{{ selectedCustomer.name }}</h4>
                                        <span v-if="selectedCustomer.nickname" class="px-2 py-0.5 rounded-md text-xs font-medium bg-white border border-slate-200 text-slate-700">
                                            "{{ selectedCustomer.nickname }}"
                                        </span>
                                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Sin Cuenta
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1">
                                        <span class="font-mono font-semibold text-slate-700">Código: {{ selectedCustomer.code }}</span>
                                        <span v-if="selectedCustomer.phone">&bull; Tel: {{ selectedCustomer.phone }}</span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="clearCustomerSelection"
                                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition shadow-2xs cursor-pointer self-start sm:self-auto"
                            >
                                <i class="fa-solid fa-rotate-left text-slate-400"></i>
                                <span>Cambiar cliente</span>
                            </button>
                        </div>

                        <!-- 1. AVISO: PEDIDO ACTIVO -->
                        <div
                            v-if="hasActiveOrder"
                            class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start justify-between gap-3"
                        >
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base mt-0.5"></i>
                                <div>
                                    <h5 class="text-xs font-bold text-amber-950 uppercase tracking-wider">
                                        Cliente con pedido activo
                                    </h5>
                                    <p class="text-xs text-amber-800 mt-0.5">
                                        El cliente ya cuenta con un pedido activo con folio
                                        <span class="font-mono font-bold">{{ selectedCustomer.active_order.folio }}</span>
                                        en estado <span class="font-semibold">{{ selectedCustomer.active_order.status_label }}</span>.
                                        Solo se permite un pedido activo por cliente a la vez.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('admin.orders.show', selectedCustomer.active_order.id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-amber-900 bg-amber-100 hover:bg-amber-200 transition shrink-0 cursor-pointer"
                            >
                                <span>Ver pedido</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </Link>
                        </div>

                        <!-- 2. AVISO: FALTA DE DOMICILIO -->
                        <div
                            v-else-if="!hasAddress"
                            class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 flex items-start justify-between gap-3"
                        >
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-location-crosshairs text-rose-600 text-base mt-0.5"></i>
                                <div>
                                    <h5 class="text-xs font-bold text-rose-950 uppercase tracking-wider">
                                        Sin domicilio de entrega
                                    </h5>
                                    <p class="text-xs text-rose-800 mt-0.5">
                                        Este cliente no tiene un domicilio de entrega activo registrado. Es necesario registrar uno para poder generar el pedido.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('admin.customers.addresses.create', selectedCustomer.id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 transition shrink-0 cursor-pointer"
                            >
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                <span>Registrar domicilio</span>
                            </Link>
                        </div>

                        <!-- 3. DOMICILIO ACTIVO DEL CLIENTE -->
                        <div v-else class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-start gap-3 text-xs">
                            <i class="fa-solid fa-location-dot text-blue-900 text-sm mt-0.5 shrink-0"></i>
                            <div class="flex-1">
                                <span class="font-bold text-slate-800">Domicilio de entrega: </span>
                                <span class="text-slate-600">{{ selectedCustomer.address.full_address }}</span>
                                <span v-if="selectedCustomer.address.zone_label" class="ml-2 px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-semibold text-[10px]">
                                    Zona {{ selectedCustomer.address.zone_label }}
                                </span>
                            </div>
                        </div>

                        <!-- 4. AVISO: SIN BOTELLONES REGISTRADOS -->
                        <div
                            v-if="hasNoBottles"
                            class="p-4 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 text-xs"
                        >
                            <i class="fa-solid fa-circle-exclamation text-slate-500 mr-1.5"></i>
                            El cliente no tiene botellones de 20 litros registrados en su inventario. Actualiza su inventario en la sección de clientes antes de registrar pedidos.
                        </div>

                        <!-- 5. INVENTARIO DISPONIBLE DEL CLIENTE -->
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-1">
                            <div class="p-3 rounded-xl bg-blue-50/60 border border-blue-200/70 text-center">
                                <span class="text-[11px] font-bold text-blue-900 uppercase tracking-wider block">Fijos Registrados</span>
                                <span class="text-lg font-black text-blue-950 mt-0.5 block">{{ registeredFixed }}</span>
                                <span class="text-[10px] text-blue-800">20 Litros</span>
                            </div>
                            <div class="p-3 rounded-xl bg-sky-50/60 border border-sky-200/70 text-center">
                                <span class="text-[11px] font-bold text-sky-900 uppercase tracking-wider block">Cambio Registrados</span>
                                <span class="text-lg font-black text-sky-950 mt-0.5 block">{{ registeredExchange }}</span>
                                <span class="text-[10px] text-sky-800">20 Litros</span>
                            </div>
                            <div class="col-span-2 sm:col-span-1 p-3 rounded-xl bg-slate-100/70 border border-slate-200 text-center">
                                <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wider block">Total en Posesión</span>
                                <span class="text-lg font-black text-slate-900 mt-0.5 block">{{ totalRegistered }}</span>
                                <span class="text-[10px] text-slate-600">Botellones de 20L</span>
                            </div>
                        </div>

                        <InputError :message="form.errors.active_order" class="mt-2" />
                        <InputError :message="form.errors.customer_id" class="mt-2" />
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 3. SELECCIÓN DE CANTIDADES (BOTELLONES DE 20 LITROS) -->
                <!-- ========================================================================= -->
                <div v-if="selectedCustomer && !hasActiveOrder && hasAddress && !hasNoBottles" class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center text-sm font-bold">
                                2
                            </div>
                            <h3 class="text-base font-bold text-slate-900">
                                Cantidades solicitadas (Botellones 20 Litros)
                            </h3>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                            Total seleccionado: {{ totalSelected }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <!-- A. Botellón Fijo (20L) -->
                        <div class="p-4 sm:p-5 rounded-2xl border transition"
                            :class="registeredFixed > 0 ? 'bg-white border-slate-200 hover:border-blue-300' : 'bg-slate-50/60 border-slate-200/60 opacity-60'"
                        >
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold text-slate-900">Botellón Fijo</h4>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-100 text-blue-900">
                                            20L
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Máximo permitido: <span class="font-bold text-slate-800">{{ registeredFixed }}</span>
                                    </p>
                                </div>
                                <button
                                    v-if="registeredFixed > 0"
                                    type="button"
                                    @click="setMaxFixed"
                                    class="text-[11px] font-bold text-blue-900 hover:text-blue-700 transition cursor-pointer px-2 py-1 rounded bg-blue-50"
                                >
                                    Solicitar todos
                                </button>
                            </div>

                            <div v-if="registeredFixed > 0" class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="decrementFixed"
                                    :disabled="form.fixed_quantity <= 0"
                                    class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-700 font-bold flex items-center justify-center transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <input
                                    type="number"
                                    v-model.number="form.fixed_quantity"
                                    min="0"
                                    :max="registeredFixed"
                                    class="w-20 text-center font-bold text-base py-2 rounded-xl border-slate-200 focus:border-blue-900 focus:ring-blue-900"
                                />
                                <button
                                    type="button"
                                    @click="incrementFixed"
                                    :disabled="form.fixed_quantity >= registeredFixed"
                                    class="h-10 w-10 rounded-xl bg-blue-900 hover:bg-blue-800 disabled:opacity-40 text-white font-bold flex items-center justify-center transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>

                            <div v-else class="text-xs text-slate-500 italic mt-2">
                                El cliente no cuenta con botellones fijos registrados.
                            </div>

                            <InputError :message="form.errors.fixed_quantity" class="mt-2" />
                        </div>

                        <!-- B. Botellón de Cambio (20L) -->
                        <div class="p-4 sm:p-5 rounded-2xl border transition"
                            :class="registeredExchange > 0 ? 'bg-white border-slate-200 hover:border-sky-300' : 'bg-slate-50/60 border-slate-200/60 opacity-60'"
                        >
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold text-slate-900">Botellón de Cambio</h4>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-sky-100 text-sky-900">
                                            20L
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Máximo permitido: <span class="font-bold text-slate-800">{{ registeredExchange }}</span>
                                    </p>
                                </div>
                                <button
                                    v-if="registeredExchange > 0"
                                    type="button"
                                    @click="setMaxExchange"
                                    class="text-[11px] font-bold text-sky-900 hover:text-sky-700 transition cursor-pointer px-2 py-1 rounded bg-sky-50"
                                >
                                    Solicitar todos
                                </button>
                            </div>

                            <div v-if="registeredExchange > 0" class="flex items-center gap-3">
                                <button
                                    type="button"
                                    @click="decrementExchange"
                                    :disabled="form.exchange_quantity <= 0"
                                    class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-700 font-bold flex items-center justify-center transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-minus text-xs"></i>
                                </button>
                                <input
                                    type="number"
                                    v-model.number="form.exchange_quantity"
                                    min="0"
                                    :max="registeredExchange"
                                    class="w-20 text-center font-bold text-base py-2 rounded-xl border-slate-200 focus:border-blue-900 focus:ring-blue-900"
                                />
                                <button
                                    type="button"
                                    @click="incrementExchange"
                                    :disabled="form.exchange_quantity >= registeredExchange"
                                    class="h-10 w-10 rounded-xl bg-blue-900 hover:bg-blue-800 disabled:opacity-40 text-white font-bold flex items-center justify-center transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-xs"></i>
                                </button>
                            </div>

                            <div v-else class="text-xs text-slate-500 italic mt-2">
                                El cliente no cuenta con botellones de cambio registrados.
                            </div>

                            <InputError :message="form.errors.exchange_quantity" class="mt-2" />
                        </div>
                    </div>

                    <InputError :message="form.errors.quantity" class="mt-2" />

                    <!-- C. Observaciones del pedido -->
                    <div class="pt-4 border-t border-slate-100 space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="order_notes" class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Observaciones de entrega (opcional)
                            </label>
                            <span class="text-[11px] text-slate-400">
                                {{ (form.notes || '').length }} / 500 caracteres
                            </span>
                        </div>
                        <textarea
                            id="order_notes"
                            v-model="form.notes"
                            rows="2"
                            maxlength="500"
                            placeholder="Indicaciones especiales de entrega (ej. portón café, tocar fuerte, entregar en horario matutino)..."
                            class="w-full rounded-xl border-slate-200 text-xs sm:text-sm focus:border-blue-900 focus:ring-blue-900 transition placeholder:text-slate-400"
                        ></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 4. RESUMEN PREVIO Y BOTÓN DE CONFIRMACIÓN DE ENVÍO -->
                <!-- ========================================================================= -->
                <div v-if="selectedCustomer && !hasActiveOrder && hasAddress && !hasNoBottles" class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-5">
                    <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                        <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center text-sm font-bold">
                            3
                        </div>
                        <h3 class="text-base font-bold text-slate-900">
                            Resumen del pedido
                        </h3>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-50/80 border border-slate-200/90 divide-y divide-slate-200/60 text-xs sm:text-sm">
                        <div class="pb-3 flex justify-between items-center">
                            <span class="text-slate-600">Cliente solicitante:</span>
                            <span class="font-bold text-slate-900">{{ selectedCustomer.name }} ({{ selectedCustomer.code }})</span>
                        </div>
                        <div class="py-3 flex justify-between items-center">
                            <span class="text-slate-600">Domicilio de entrega:</span>
                            <span class="font-medium text-slate-800 text-right max-w-[65%]">{{ selectedCustomer.address.full_address }}</span>
                        </div>
                        <div class="py-3 flex justify-between items-center">
                            <span class="text-slate-600">Botellones fijos (20L):</span>
                            <span class="font-bold text-blue-900">{{ form.fixed_quantity }} unidades</span>
                        </div>
                        <div class="py-3 flex justify-between items-center">
                            <span class="text-slate-600">Botellones de cambio (20L):</span>
                            <span class="font-bold text-sky-900">{{ form.exchange_quantity }} unidades</span>
                        </div>
                        <div class="pt-3 flex justify-between items-center text-sm font-black text-slate-900">
                            <span>Total de botellones solicitados:</span>
                            <span class="text-base text-blue-900">{{ totalSelected }} botellones (20L)</span>
                        </div>
                    </div>

                    <InputError :message="form.errors.schedule" class="mt-2" />

                    <!-- Botón Enviar -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-3">
                        <Link
                            :href="route('admin.orders.index')"
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 transition text-center cursor-pointer"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="button"
                            @click="submitOrder"
                            :disabled="!canSubmit"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-xs cursor-pointer"
                        >
                            <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-sky-400"></i>
                            <i v-else class="fa-solid fa-check-circle text-sky-400"></i>
                            <span>{{ form.processing ? 'Registrando Pedido...' : 'Registrar Pedido' }}</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
