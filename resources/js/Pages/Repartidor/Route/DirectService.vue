<script setup>
import { ref, computed } from 'vue';
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
// 1. CLIENTE: BÚSQUEDA O NUEVO RÁPIDO
// -------------------------------------------------------------------------
const clientMode = ref('search'); // 'search' o 'new'

// Búsqueda de cliente existente
const searchQuery = ref(props.initialCustomer?.nickname || '');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedCustomer = ref(props.initialCustomer || null);

// Elección de servicio: 'fijos' O 'cambios'
const selectedService = ref(
    props.initialCustomer?.bottle_type === 'fixed'
        ? 'fijos'
        : props.initialCustomer?.bottle_type === 'exchange'
        ? 'cambios'
        : null
);

// Cantidad única del servicio seleccionado
const quantity = ref(1);

const isCustomerLocked = computed(() => {
    return clientMode.value === 'search' && 
        selectedCustomer.value && 
        (selectedCustomer.value.bottle_type === 'fixed' || selectedCustomer.value.bottle_type === 'exchange');
});

const isCustomerUnassigned = computed(() => {
    return (clientMode.value === 'search' && selectedCustomer.value && selectedCustomer.value.bottle_type === 'unassigned') || clientMode.value === 'new';
});

const selectService = (service) => {
    selectedService.value = service;
    quantity.value = 1;
};

const incrementQty = () => {
    if (selectedService.value === 'fijos') {
        quantity.value++;
    } else if (selectedService.value === 'cambios') {
        const maxAvailable = Number(props.truck?.exchange_full_available || 0);
        if (quantity.value < maxAvailable) {
            quantity.value++;
        }
    }
};

const decrementQty = () => {
    if (quantity.value > 1) {
        quantity.value--;
    }
};

const setQuantityValue = (val) => {
    const num = parseInt(val, 10);
    if (isNaN(num) || num < 1) {
        quantity.value = 1;
        return;
    }
    if (selectedService.value === 'cambios') {
        const maxAvailable = Number(props.truck?.exchange_full_available || 0);
        quantity.value = Math.min(num, Math.max(1, maxAvailable));
    } else {
        quantity.value = num;
    }
};

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
    if (cust.bottle_type === 'fixed') {
        selectedService.value = 'fijos';
    } else if (cust.bottle_type === 'exchange') {
        selectedService.value = 'cambios';
    } else {
        selectedService.value = null;
    }
    quantity.value = 1;
};

const clearSelectedCustomer = () => {
    selectedCustomer.value = null;
    searchQuery.value = '';
    searchResults.value = [];
    selectedService.value = null;
    quantity.value = 1;
};

const setClientMode = (mode) => {
    clientMode.value = mode;
    if (mode === 'new') {
        selectedService.value = null;
        quantity.value = 1;
    } else {
        if (selectedCustomer.value) {
            if (selectedCustomer.value.bottle_type === 'fixed') {
                selectedService.value = 'fijos';
            } else if (selectedCustomer.value.bottle_type === 'exchange') {
                selectedService.value = 'cambios';
            } else {
                selectedService.value = null;
            }
        } else {
            selectedService.value = null;
        }
        quantity.value = 1;
    }
};

// Formulario de nuevo cliente rápido
const availableZones = [
    { id: 'north', label: 'Norte' },
    { id: 'south', label: 'Sur' },
    { id: 'east', label: 'Este' },
    { id: 'west', label: 'Oeste' },
];

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

// Validación de pedido activo en cliente seleccionado
const hasActiveOrder = computed(() => {
    return clientMode.value === 'search' && !!selectedCustomer.value?.active_order;
});

// -------------------------------------------------------------------------
// 2. ENVÍO DEL PEDIDO AL PASO
// -------------------------------------------------------------------------
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
    service_type: '',
    exchange_quantity: 0,
    exchange_empty_received: 0,
    fixed_quantity: 0,
    units_paid_cash: 0,
    cash_tendered: 0,
});

const submitDirectOrder = () => {
    if (!selectedService.value) {
        alert('Por favor elige primero el servicio: Fijos o Cambios.');
        return;
    }

    if (clientMode.value === 'search' && !selectedCustomer.value) {
        alert('Debes buscar y seleccionar un cliente o registrar un Nuevo Cliente Rápido.');
        return;
    }

    if (hasActiveOrder.value) {
        alert('Este cliente ya cuenta con un pedido activo. No se puede registrar otro pedido hasta que concluya el actual.');
        return;
    }

    if (clientMode.value === 'new') {
        if (!newCustomer.value.nickname.trim()) {
            alert('El apodo es obligatorio para registrar un nuevo cliente.');
            return;
        }
        if (!newCustomer.value.delivery_zone) {
            alert('Por favor especifica la zona de reparto del nuevo cliente (Norte, Sur, Este u Oeste).');
            return;
        }
    }

    if (selectedService.value === 'cambios') {
        const fulls = Number(props.truck?.exchange_full_available || 0);
        if (fulls <= 0) {
            alert('No tienes cambios llenos disponibles en tu camión.');
            return;
        }
        if (quantity.value > fulls) {
            alert(`Solo tienes ${fulls} botellones llenos disponibles.`);
            return;
        }
    }

    if (clientMode.value === 'search') {
        form.customer_id = selectedCustomer.value.id;
        form.nickname = '';
        form.name = null;
        form.phone = null;
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

    if (selectedService.value === 'fijos') {
        form.service_type = 'fijos';
        form.fixed_quantity = quantity.value;
        form.exchange_quantity = 0;
        form.exchange_empty_received = 0;
        form.units_paid_cash = 0;
        form.cash_tendered = 0;
    } else {
        form.service_type = 'cambios';
        form.fixed_quantity = 0;
        form.exchange_quantity = quantity.value;
        form.exchange_empty_received = quantity.value;
        form.units_paid_cash = quantity.value;
        form.cash_tendered = Math.round(quantity.value * props.unit_price * 100) / 100;
    }

    form.post(route('repartidor.direct-service.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Pedido al paso · Reparto" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto space-y-4 pb-16 px-2 sm:px-4">

            <!-- ========================================================================= -->
            <!-- ENCABEZADO Y REGRESO A REPARTO                                            -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-3 pt-1">
                <Link
                    :href="route('repartidor.reparto')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-50 active:scale-95 transition"
                >
                    <i class="fa-solid fa-arrow-left text-slate-500"></i>
                    <span>Volver a Reparto</span>
                </Link>

                <div class="text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Servicio en Ruta</span>
                    <span class="text-sm font-extrabold text-blue-900">Pedido al paso</span>
                </div>
            </div>

            <!-- Alerta flash de error -->
            <div v-if="flashError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold flex items-center gap-2 shadow-2xs">
                <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
                <span>{{ flashError }}</span>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. SELECCIÓN DE CLIENTE (Buscar o Alta Rápida)                             -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div>
                        <h2 class="text-sm sm:text-base font-black text-slate-900">1. Cliente</h2>
                        <p class="text-xs text-slate-500">Busca el cliente o regístralo rápidamente</p>
                    </div>

                    <!-- Toggle de modo de cliente -->
                    <div class="inline-flex p-1 bg-slate-100 rounded-xl gap-1">
                        <button
                            type="button"
                            @click="setClientMode('search')"
                            class="px-3 py-1.5 text-xs font-black rounded-lg transition"
                            :class="clientMode === 'search' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="fa-solid fa-magnifying-glass mr-1"></i> Buscar
                        </button>
                        <button
                            type="button"
                            @click="setClientMode('new')"
                            class="px-3 py-1.5 text-xs font-black rounded-lg transition"
                            :class="clientMode === 'new' ? 'bg-blue-900 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                        >
                            <i class="fa-solid fa-user-plus mr-1"></i> Nuevo rápido
                        </button>
                    </div>
                </div>

                <!-- MODO A: BUSCADOR -->
                <div v-if="clientMode === 'search'" class="space-y-3">
                    <div v-if="!selectedCustomer" class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">
                            Buscar por apodo, nombre, código o teléfono:
                        </label>
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Ej. Don Pancho, Doña Mari, CLI-001..."
                                class="w-full text-sm py-2.5 pl-9 pr-3 rounded-xl border border-slate-200 bg-white focus:border-blue-900 focus:ring-1 focus:ring-blue-900 transition"
                            />
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3.5 text-slate-400 text-xs"></i>
                        </div>

                        <!-- Resultados -->
                        <div v-if="isSearching" class="text-xs text-slate-400 py-2 text-center">Buscando clientes...</div>
                        <div v-else-if="searchResults.length > 0" class="divide-y divide-slate-100 border border-slate-100 rounded-xl max-h-48 overflow-y-auto shadow-2xs">
                            <button
                                v-for="c in searchResults"
                                :key="c.id"
                                @click="selectCustomer(c)"
                                type="button"
                                class="w-full p-2.5 text-left hover:bg-blue-50/70 flex items-center justify-between text-xs transition"
                            >
                                <div>
                                    <span class="font-bold text-slate-900 text-sm block">{{ c.nickname }}</span>
                                    <span v-if="c.name" class="text-slate-500 text-[11px]">{{ c.name }}</span>
                                    <span v-if="c.bottle_type_label" class="text-slate-500 text-[10px] block mt-0.5">
                                        {{ c.bottle_type_label }} ({{ c.assigned_bottles_count }})
                                    </span>
                                </div>
                                <span class="text-[11px] font-mono text-slate-400 bg-slate-50 px-2 py-0.5 rounded">{{ c.code }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cliente seleccionado -->
                    <div v-else class="space-y-3">
                        <div class="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200 text-xs flex items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <span class="font-mono text-[10px] text-blue-700 font-bold block">{{ selectedCustomer.code }}</span>
                                <span class="text-base font-black text-blue-950 block">{{ selectedCustomer.nickname }}</span>
                                <span v-if="selectedCustomer.name" class="text-slate-600 text-[11px] block">{{ selectedCustomer.name }}</span>
                                <span v-if="selectedCustomer.phone" class="text-slate-500 text-[11px] block"><i class="fa-solid fa-phone text-[10px]"></i> {{ selectedCustomer.phone }}</span>
                                
                                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                    <span v-if="selectedCustomer.address?.zone_label" class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-900 bg-blue-100/70 px-2 py-0.5 rounded-md">
                                        <i class="fa-solid fa-location-dot text-[10px]"></i>
                                        {{ selectedCustomer.address.zone_label }}
                                    </span>

                                    <!-- Etiqueta de modalidad exclusiva -->
                                    <span
                                        v-if="selectedCustomer.bottle_type === 'fixed'"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-900 bg-blue-100 border border-blue-300 px-2 py-0.5 rounded-md"
                                    >
                                        <i class="fa-solid fa-bottle-water"></i>
                                        Fijos ({{ selectedCustomer.assigned_bottles_count }})
                                    </span>
                                    <span
                                        v-else-if="selectedCustomer.bottle_type === 'exchange'"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-900 bg-emerald-100 border border-emerald-300 px-2 py-0.5 rounded-md"
                                    >
                                        <i class="fa-solid fa-arrows-rotate"></i>
                                        Cambios ({{ selectedCustomer.assigned_bottles_count }})
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md"
                                    >
                                        Sin botellones asignados
                                    </span>
                                </div>
                            </div>
                            <button
                                @click="clearSelectedCustomer"
                                type="button"
                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 active:scale-95 transition shadow-2xs shrink-0"
                            >
                                Cambiar
                            </button>
                        </div>

                        <!-- ALERTA SI TIENE PEDIDO ACTIVO -->
                        <div
                            v-if="hasActiveOrder"
                            class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs space-y-2"
                        >
                            <div class="flex items-center gap-2 font-black text-amber-900">
                                <i class="fa-solid fa-triangle-exclamation text-amber-700 text-base"></i>
                                <span>El cliente ya cuenta con un pedido activo</span>
                            </div>
                            <p class="text-amber-800 leading-relaxed">
                                Pedido <strong>{{ selectedCustomer.active_order.folio }}</strong> en estado <strong>{{ selectedCustomer.active_order.status_label }}</strong>.
                                Por regla de negocio, cada cliente tiene un único pedido activo a la vez. No es posible crear otro pedido al paso hasta que concluya.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- MODO B: ALTA RÁPIDA -->
                <div v-else class="space-y-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            Apodo del cliente <span class="text-rose-500">*</span>:
                        </label>
                        <input
                            v-model="newCustomer.nickname"
                            type="text"
                            placeholder="Ej. Tienda Doña Mary, Taller Mecánico..."
                            class="w-full text-sm py-2 px-3 rounded-xl border border-slate-200 bg-white focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nombre (opcional):</label>
                            <input
                                v-model="newCustomer.name"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full text-xs py-2 px-3 rounded-xl border border-slate-200 bg-white focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
                            />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Teléfono (opcional):</label>
                            <input
                                v-model="newCustomer.phone"
                                type="text"
                                placeholder="10 dígitos"
                                class="w-full text-xs py-2 px-3 rounded-xl border border-slate-200 bg-white focus:border-blue-900 focus:ring-1 focus:ring-blue-900"
                            />
                        </div>
                    </div>

                    <!-- Selector de Zona de Reparto -->
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">
                            Zona de reparto <span class="text-rose-500">*</span>:
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button
                                v-for="zone in availableZones"
                                :key="zone.id"
                                type="button"
                                @click="newCustomer.delivery_zone = zone.id"
                                class="py-2.5 px-3 rounded-xl text-xs font-bold border transition flex items-center justify-center gap-1.5 min-h-[44px] select-none cursor-pointer"
                                :class="newCustomer.delivery_zone === zone.id
                                    ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 active:bg-slate-100'"
                            >
                                <i
                                    class="fa-solid fa-location-dot text-xs"
                                    :class="newCustomer.delivery_zone === zone.id ? 'text-blue-300' : 'text-slate-400'"
                                ></i>
                                <span>{{ zone.label }}</span>
                            </button>
                        </div>
                        <p v-if="!newCustomer.delivery_zone" class="text-[11px] text-amber-600 mt-1 flex items-center gap-1 font-medium">
                            <i class="fa-solid fa-circle-info"></i> Selecciona la zona para ubicar al cliente en el listado de reparto.
                        </p>
                    </div>

                    <p class="text-[11px] text-slate-400 italic">
                        * No se exige cuenta de correo, contraseña ni domicilio detallado para registrar al cliente en el momento.
                    </p>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. SERVICIO Y CANTIDAD                                                    -->
            <!-- ========================================================================= -->

            <!-- Si está en modo búsqueda y no hay cliente seleccionado -->
            <div
                v-if="clientMode === 'search' && !selectedCustomer"
                class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-xs text-center space-y-2 text-slate-400"
            >
                <i class="fa-solid fa-user-tag text-3xl text-slate-300"></i>
                <p class="text-xs font-bold text-slate-600">Busca y selecciona un cliente o elige "Nuevo rápido" para configurar el servicio y la cantidad.</p>
            </div>

            <!-- Bloque de configuración del servicio -->
            <div v-else class="space-y-4">

                <!-- Si el cliente es nuevo o no tiene botellones asignados: Ofrecer selector Fijos / Cambios -->
                <div
                    v-if="isCustomerUnassigned"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-3"
                >
                    <div>
                        <h2 class="text-sm sm:text-base font-black text-slate-900">2. Elige el tipo de servicio</h2>
                        <p class="text-xs text-slate-500">Define la modalidad de botellones para este cliente</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- BOTÓN FIJOS -->
                        <button
                            type="button"
                            @click="selectService('fijos')"
                            class="p-4 rounded-2xl border-2 text-left transition-all duration-150 flex items-start gap-3.5 active:scale-98 relative cursor-pointer"
                            :class="selectedService === 'fijos'
                                ? 'border-blue-900 bg-blue-50/70 shadow-sm ring-2 ring-blue-900/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0 transition"
                                :class="selectedService === 'fijos' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600'"
                            >
                                <i class="fa-solid fa-bottle-water"></i>
                            </div>
                            <div class="flex-1 min-w-0 pr-6">
                                <div class="text-base font-black text-slate-900">Fijos</div>
                                <p class="text-xs text-slate-600 mt-0.5 leading-snug">Recoger para llenar en Planta</p>
                            </div>
                            <div
                                v-if="selectedService === 'fijos'"
                                class="absolute top-3.5 right-3.5 w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs shadow-2xs"
                            >
                                <i class="fa-solid fa-check"></i>
                            </div>
                        </button>

                        <!-- BOTÓN CAMBIOS -->
                        <button
                            type="button"
                            @click="selectService('cambios')"
                            class="p-4 rounded-2xl border-2 text-left transition-all duration-150 flex items-start gap-3.5 active:scale-98 relative cursor-pointer"
                            :class="selectedService === 'cambios'
                                ? 'border-blue-900 bg-blue-50/70 shadow-sm ring-2 ring-blue-900/20'
                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'"
                        >
                            <div
                                class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0 transition"
                                :class="selectedService === 'cambios' ? 'bg-blue-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600'"
                            >
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                            <div class="flex-1 min-w-0 pr-6">
                                <div class="text-base font-black text-slate-900">Cambios</div>
                                <p class="text-xs text-slate-600 mt-0.5 leading-snug">Cambiar vacíos por llenos</p>
                            </div>
                            <div
                                v-if="selectedService === 'cambios'"
                                class="absolute top-3.5 right-3.5 w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs shadow-2xs"
                            >
                                <i class="fa-solid fa-check"></i>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Si el cliente ya tiene modalidad exclusiva asignada: Mostrar tarjeta informativa fija -->
                <div
                    v-else-if="isCustomerLocked"
                    class="bg-blue-50/60 rounded-2xl p-4 border border-blue-200/80 flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center text-base shrink-0">
                            <i :class="selectedService === 'fijos' ? 'fa-solid fa-bottle-water' : 'fa-solid fa-arrows-rotate'"></i>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 block">Modalidad exclusiva</span>
                            <span class="text-sm font-black text-blue-950">
                                {{ selectedService === 'fijos' ? 'Botellones Fijos' : 'Botellones de Cambio' }}
                            </span>
                            <span class="text-xs text-slate-600 block">
                                {{ selectedService === 'fijos' ? 'Recogida física para llenar en Planta' : 'Intercambio inmediato en ruta' }}
                            </span>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-blue-800 bg-white border border-blue-200 px-3 py-1 rounded-xl shadow-2xs">
                        {{ selectedCustomer.assigned_bottles_count }} reg.
                    </span>
                </div>

                <!-- CASO A: SI EL SERVICIO ES FIJOS -->
                <div
                    v-if="selectedService === 'fijos'"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
                >
                    <div class="border-b border-slate-100 pb-2.5">
                        <h2 class="text-sm sm:text-base font-black text-slate-900">Cantidad</h2>
                        <p class="text-xs text-slate-500">¿Cuántos fijos recogiste?</p>
                    </div>

                    <!-- Campo de cantidad con stepper táctil grande -->
                    <div class="space-y-3 py-2">
                        <label class="block text-center text-xs font-bold uppercase tracking-wider text-slate-500">
                            Botellones fijos de 20 L a recoger:
                        </label>

                        <div class="flex items-center justify-center gap-4">
                            <!-- Botón Menos (>= 48x48px) -->
                            <button
                                type="button"
                                @click="decrementQty"
                                :disabled="quantity <= 1"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-slate-100 hover:bg-slate-200 font-black text-2xl text-slate-700 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition flex items-center justify-center shadow-2xs cursor-pointer"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <!-- Input / Visualizador de Cantidad -->
                            <div class="text-center">
                                <input
                                    :value="quantity"
                                    @input="setQuantityValue($event.target.value)"
                                    type="number"
                                    min="1"
                                    class="w-24 sm:w-28 text-center text-3xl sm:text-4xl font-black text-blue-950 bg-slate-50 border-2 border-slate-200 rounded-2xl py-2.5 focus:border-blue-900 focus:bg-white transition"
                                />
                                <span class="block text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">
                                    {{ quantity === 1 ? 'Botellón fijo' : 'Botellones fijos' }}
                                </span>
                            </div>

                            <!-- Botón Más (>= 48x48px) -->
                            <button
                                type="button"
                                @click="incrementQty"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-blue-900 hover:bg-blue-800 font-black text-2xl text-white active:scale-95 transition flex items-center justify-center shadow-sm cursor-pointer"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>

                        <p class="text-[11px] text-center text-slate-500 pt-1">
                            Esta operación se puede registrar aunque el camión tenga cero cambios llenos. Las unidades pasan directamente a <strong>Planta → Por recibir</strong>.
                        </p>
                    </div>

                    <!-- Resumen previo al botón -->
                    <div class="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200/80 text-center font-bold text-blue-950 text-xs sm:text-sm">
                        Recoges {{ quantity }} {{ quantity === 1 ? 'fijo' : 'fijos' }} para enviar a Planta
                    </div>

                    <!-- Botón de Confirmación Principal -->
                    <button
                        type="button"
                        @click="submitDirectOrder"
                        :disabled="form.processing || hasActiveOrder || (clientMode === 'search' && !selectedCustomer) || (clientMode === 'new' && !newCustomer.nickname.trim())"
                        class="w-full py-4 rounded-2xl text-base font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-lg flex items-center justify-center gap-2 disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>Registrar recogida de {{ quantity }} {{ quantity === 1 ? 'fijo' : 'fijos' }}</span>
                    </button>
                </div>

                <!-- CASO B: SI EL SERVICIO ES CAMBIOS -->
                <div
                    v-if="selectedService === 'cambios'"
                    class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs space-y-4"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div>
                            <h2 class="text-sm sm:text-base font-black text-slate-900">Cantidad</h2>
                            <p class="text-xs text-slate-500">¿Cuántos cambios vas a hacer?</p>
                        </div>

                        <!-- Llenos disponibles en el camión -->
                        <div
                            class="px-3 py-1.5 rounded-full text-xs font-black inline-flex items-center gap-1.5"
                            :class="truck.exchange_full_available > 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'"
                        >
                            <i class="fa-solid fa-truck"></i>
                            <span>{{ truck.exchange_full_available }} llenos en camión</span>
                        </div>
                    </div>

                    <!-- Aviso si camión tiene 0 cambios llenos -->
                    <div
                        v-if="truck.exchange_full_available <= 0"
                        class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-950 text-xs space-y-2"
                    >
                        <div class="flex items-center gap-2 font-black text-amber-900 text-sm">
                            <i class="fa-solid fa-triangle-exclamation text-amber-700"></i>
                            <span>No tienes cambios llenos disponibles</span>
                        </div>
                        <p class="text-amber-800 leading-relaxed">
                            Tu camión no cuenta con botellones de cambio llenos para entregar en este momento.
                        </p>
                    </div>

                    <!-- Campo de cantidad con stepper táctil si hay existencias -->
                    <div v-else class="space-y-3 py-2">
                        <label class="block text-center text-xs font-bold uppercase tracking-wider text-slate-500">
                            Cantidad de cambios a realizar:
                        </label>

                        <div class="flex items-center justify-center gap-4">
                            <!-- Botón Menos (>= 48x48px) -->
                            <button
                                type="button"
                                @click="decrementQty"
                                :disabled="quantity <= 1"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-slate-100 hover:bg-slate-200 font-black text-2xl text-slate-700 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition flex items-center justify-center shadow-2xs cursor-pointer"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <!-- Input / Visualizador de Cantidad -->
                            <div class="text-center">
                                <input
                                    :value="quantity"
                                    @input="setQuantityValue($event.target.value)"
                                    type="number"
                                    min="1"
                                    :max="truck.exchange_full_available"
                                    class="w-24 sm:w-28 text-center text-3xl sm:text-4xl font-black text-blue-950 bg-slate-50 border-2 border-slate-200 rounded-2xl py-2.5 focus:border-blue-900 focus:bg-white transition"
                                />
                                <span class="block text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">
                                    {{ quantity === 1 ? 'Cambio' : 'Cambios' }}
                                </span>
                            </div>

                            <!-- Botón Más (>= 48x48px) -->
                            <button
                                type="button"
                                @click="incrementQty"
                                :disabled="quantity >= truck.exchange_full_available"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-blue-900 hover:bg-blue-800 font-black text-2xl text-white active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition flex items-center justify-center shadow-sm cursor-pointer"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>

                        <p class="text-[11px] text-center text-slate-400">
                            Máximo disponible en camión: {{ truck.exchange_full_available }} unidades
                        </p>
                    </div>

                    <!-- Resumen automático inmediatamente arriba del botón -->
                    <div
                        v-if="truck.exchange_full_available > 0"
                        class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-center space-y-1"
                    >
                        <p class="text-sm sm:text-base font-black text-blue-950">
                            Entregas {{ quantity }} {{ quantity === 1 ? 'lleno' : 'llenos' }} · Recibes {{ quantity }} {{ quantity === 1 ? 'vacío' : 'vacíos' }}
                        </p>
                        <p class="text-xs text-blue-800 font-bold">
                            {{ quantity }} × ${{ unit_price }} = ${{ Math.round(quantity * unit_price * 100) / 100 }} MXN
                        </p>
                    </div>

                    <!-- Botón de Confirmación Principal -->
                    <button
                        v-if="truck.exchange_full_available > 0"
                        type="button"
                        @click="submitDirectOrder"
                        :disabled="form.processing || hasActiveOrder || (clientMode === 'search' && !selectedCustomer) || (clientMode === 'new' && !newCustomer.nickname.trim())"
                        class="w-full py-4 rounded-2xl text-base font-black bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition shadow-lg flex items-center justify-center gap-2 disabled:opacity-40 disabled:pointer-events-none cursor-pointer"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>Confirmar {{ quantity }} {{ quantity === 1 ? 'cambio' : 'cambios' }}</span>
                    </button>
                </div>

            </div>

        </div>
    </AuthenticatedLayout>
</template>
