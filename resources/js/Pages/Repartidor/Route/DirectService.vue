<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { alertWarning } from '@/utils/swal';
import {
    ArrowLeft,
    Search,
    UserPlus,
    Phone,
    MapPin,
    Package,
    RefreshCw,
    AlertTriangle,
    Truck,
    Check,
    Minus,
    Plus,
    UserRound,
} from 'lucide-vue-next';

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
const flashError = computed(() => page.props.errors?.direct || null);

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(val || 0));
};

// -------------------------------------------------------------------------
// 1. CLIENTE: BÚSQUEDA O NUEVO RÁPIDO
// -------------------------------------------------------------------------
const clientMode = ref('search'); // 'search' o 'new'

const searchQuery = ref(props.initialCustomer?.nickname || '');
const searchResults = ref([]);
const isSearching = ref(false);
const selectedCustomer = ref(props.initialCustomer || null);

const selectedService = ref(
    props.initialCustomer?.has_account && props.initialCustomer?.bottle_type === 'fixed'
        ? 'fijos'
        : props.initialCustomer?.has_account && props.initialCustomer?.bottle_type === 'exchange'
        ? 'cambios'
        : null
);

const quantity = ref(1);

const isCustomerLocked = computed(() => {
    return clientMode.value === 'search' &&
        selectedCustomer.value &&
        selectedCustomer.value.has_account &&
        (selectedCustomer.value.bottle_type === 'fixed' || selectedCustomer.value.bottle_type === 'exchange');
});

const isCustomerUnassigned = computed(() => {
    return (clientMode.value === 'search' && selectedCustomer.value && !isCustomerLocked.value) || clientMode.value === 'new';
});

const selectService = (service) => {
    selectedService.value = service;
    quantity.value = 1;
};

const maxCustomerLimit = computed(() => {
    if (!isCustomerLocked.value) return Infinity;
    return Math.max(1, Number(selectedCustomer.value?.assigned_bottles_count || 1));
});

const incrementQty = () => {
    if (selectedService.value === 'fijos') {
        if (quantity.value < maxCustomerLimit.value) {
            quantity.value++;
        }
    } else if (selectedService.value === 'cambios') {
        const maxAvailable = Math.min(Number(props.truck?.exchange_full_available || 0), maxCustomerLimit.value);
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
        const maxAvailable = Math.min(Number(props.truck?.exchange_full_available || 0), maxCustomerLimit.value);
        quantity.value = Math.min(num, Math.max(1, maxAvailable));
    } else {
        quantity.value = Math.min(num, maxCustomerLimit.value);
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
    if (cust.has_account && cust.bottle_type === 'fixed') {
        selectedService.value = 'fijos';
    } else if (cust.has_account && cust.bottle_type === 'exchange') {
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
        if (selectedCustomer.value && selectedCustomer.value.has_account) {
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

const availableZones = computed(() => {
    const shared = page.props.deliveryZones;
    if (Array.isArray(shared) && shared.length > 0) {
        return shared.map((z) => ({ id: z.key, label: z.name }));
    }
    return [
        { id: 'north', label: 'Norte' },
        { id: 'south', label: 'Sur' },
        { id: 'east', label: 'Este' },
        { id: 'west', label: 'Oeste' },
    ];
});

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

const hasActiveOrder = computed(() => {
    return clientMode.value === 'search' && !!selectedCustomer.value?.active_order;
});

const exchangeTotalAmount = computed(() => {
    return Math.round(quantity.value * Number(props.unit_price || 15) * 100) / 100;
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
        alertWarning('Por favor elige primero el servicio: Fijos o Cambios.');
        return;
    }

    if (clientMode.value === 'search' && !selectedCustomer.value) {
        alertWarning('Debes buscar y seleccionar un cliente o registrar un Nuevo Cliente Rápido.');
        return;
    }

    if (hasActiveOrder.value) {
        alertWarning('Este cliente ya cuenta con un pedido activo. No se puede registrar otro pedido hasta que concluya el actual.');
        return;
    }

    if (clientMode.value === 'new') {
        if (!newCustomer.value.nickname.trim()) {
            alertWarning('El apodo es obligatorio para registrar un nuevo cliente.');
            return;
        }
        if (!newCustomer.value.delivery_zone) {
            alertWarning('Por favor selecciona la zona de reparto del nuevo cliente.');
            return;
        }
    }

    if (selectedService.value === 'cambios') {
        const fulls = Number(props.truck?.exchange_full_available || 0);
        if (fulls <= 0) {
            alertWarning('No tienes cambios llenos disponibles en tu camión.');
            return;
        }
        if (quantity.value > fulls) {
            alertWarning(`Solo tienes ${fulls} botellones llenos disponibles.`);
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
        form.cash_tendered = exchangeTotalAmount.value;
    }

    form.post(route('repartidor.direct-service.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Al paso" />

    <AuthenticatedLayout>
        <div class="bg-[#F4F3F8] py-4 sm:py-6 pb-24">
            <div class="max-w-2xl mx-auto px-3.5 sm:px-6 space-y-4">

                <!-- ========================================================================= -->
                <!-- ENCABEZADO Y REGRESO A REPARTO (UNIFICADO PURITRONIC)                     -->
                <!-- ========================================================================= -->
                <div class="flex items-center justify-between gap-3">
                    <Link
                        :href="route('repartidor.reparto')"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 min-h-[44px] rounded-xl text-xs font-bold bg-white text-[#1E1545] border border-[#E9E7F0] shadow-sm hover:bg-[#F4F3F8] active:scale-95 transition"
                    >
                        <ArrowLeft class="w-4 h-4 text-[#747181]" aria-hidden="true" />
                        <span>Volver a Reparto</span>
                    </Link>

                    <div class="text-right">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#747181] block">
                            Servicio en ruta
                        </span>
                        <span class="text-xs sm:text-sm font-extrabold text-[#1E1545]">
                            Pedido al paso
                        </span>
                    </div>
                </div>

                <!-- Alerta flash de error -->
                <div
                    v-if="flashError"
                    role="alert"
                    class="p-3.5 rounded-2xl bg-[#FCECEF] border border-[#F6C8D2] text-[#E82147] text-xs font-bold flex items-center gap-2"
                >
                    <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span>{{ flashError }}</span>
                </div>

                <!-- ========================================================================= -->
                <!-- 1. SELECCIÓN DE CLIENTE (BUSCAR O ALTA RÁPIDA)                            -->
                <!-- ========================================================================= -->
                <section class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E9E7F0] pb-3">
                        <div>
                            <h2 class="text-sm sm:text-base font-extrabold text-[#1E1545]">1. Cliente</h2>
                            <p class="text-xs text-[#747181]">Busca un cliente o regístralo rápidamente</p>
                        </div>

                        <div class="inline-flex p-1 bg-[#F4F3F8] rounded-xl border border-[#E9E7F0] gap-1">
                            <button
                                type="button"
                                @click="setClientMode('search')"
                                class="px-3 py-1.5 min-h-[36px] text-xs font-bold rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
                                :class="clientMode === 'search' ? 'bg-[#1E1545] text-white shadow-sm' : 'text-[#747181] hover:text-[#1E1545]'"
                            >
                                <Search class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>Buscar</span>
                            </button>
                            <button
                                type="button"
                                @click="setClientMode('new')"
                                class="px-3 py-1.5 min-h-[36px] text-xs font-bold rounded-lg transition inline-flex items-center gap-1.5 cursor-pointer"
                                :class="clientMode === 'new' ? 'bg-[#1E1545] text-white shadow-sm' : 'text-[#747181] hover:text-[#1E1545]'"
                            >
                                <UserPlus class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>Nuevo rápido</span>
                            </button>
                        </div>
                    </div>

                    <!-- MODO A: BUSCADOR -->
                    <div v-if="clientMode === 'search'" class="space-y-3">
                        <div v-if="!selectedCustomer" class="space-y-2">
                            <label class="block text-xs font-bold text-[#292638]">
                                Buscar por apodo, nombre o teléfono:
                            </label>
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    @input="onSearchInput"
                                    type="text"
                                    placeholder="Ej. Don Pancho, Doña Mari..."
                                    class="w-full text-base sm:text-sm py-2.5 pl-9 pr-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] focus:border-[#1E1545] focus:ring-1 focus:ring-[#1E1545] transition"
                                />
                                <Search class="w-4 h-4 absolute left-3 top-3 text-[#747181]" aria-hidden="true" />
                            </div>

                            <div v-if="isSearching" class="text-xs text-[#747181] py-2 text-center">Buscando clientes...</div>
                            <div v-else-if="searchResults.length > 0" class="divide-y divide-[#E9E7F0] border border-[#E9E7F0] rounded-xl max-h-52 overflow-y-auto">
                                <button
                                    v-for="c in searchResults"
                                    :key="c.id"
                                    @click="selectCustomer(c)"
                                    type="button"
                                    class="w-full p-3 text-left hover:bg-[#F4F3F8] flex items-center justify-between text-xs transition cursor-pointer"
                                >
                                    <div class="min-w-0">
                                        <span class="font-bold text-[#1E1545] text-sm block truncate">{{ c.nickname }}</span>
                                        <span v-if="c.name" class="text-[#747181] text-[11px] block truncate">{{ c.name }}</span>
                                        <span v-if="c.bottle_type_label" class="text-[#0071BA] text-[11px] font-semibold block mt-0.5">
                                            {{ c.bottle_type_label }} ({{ c.assigned_bottles_count }})
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Cliente seleccionado -->
                        <div v-else class="space-y-3">
                            <div class="p-3.5 rounded-xl bg-[#F4F3F8]/75 border border-[#E9E7F0] text-xs flex items-start justify-between gap-3">
                                <div class="space-y-1 min-w-0">
                                    <span class="text-base font-extrabold text-[#1E1545] block break-words">
                                        {{ selectedCustomer.nickname }}
                                    </span>
                                    <span v-if="selectedCustomer.name" class="text-[#747181] text-[11px] block truncate">
                                        {{ selectedCustomer.name }}
                                    </span>
                                    <span v-if="selectedCustomer.phone" class="text-[#747181] text-[11px] inline-flex items-center gap-1">
                                        <Phone class="w-3 h-3 text-[#26845B]" aria-hidden="true" />
                                        {{ selectedCustomer.phone }}
                                    </span>

                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span v-if="selectedCustomer.address?.zone_label" class="inline-flex items-center gap-1 text-[11px] font-bold text-[#0071BA] bg-[#E9F3F8] px-2 py-0.5 rounded-full">
                                            <MapPin class="w-3 h-3" aria-hidden="true" />
                                            {{ selectedCustomer.address.zone_label }}
                                        </span>

                                        <span
                                            v-if="selectedCustomer.bottle_type === 'fixed'"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-[#1E1545] bg-[#EEEAF7] px-2 py-0.5 rounded-full"
                                        >
                                            <Package class="w-3 h-3" aria-hidden="true" />
                                            Fijos ({{ selectedCustomer.assigned_bottles_count }})
                                        </span>
                                        <span
                                            v-else-if="selectedCustomer.bottle_type === 'exchange'"
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-[#26845B] bg-[#E8F2EC] px-2 py-0.5 rounded-full"
                                        >
                                            <RefreshCw class="w-3 h-3" aria-hidden="true" />
                                            Cambios ({{ selectedCustomer.assigned_bottles_count }})
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 text-[11px] font-bold text-[#747181] bg-white border border-[#E9E7F0] px-2 py-0.5 rounded-full"
                                        >
                                            Sin botellones asignados
                                        </span>
                                    </div>
                                </div>
                                <button
                                    @click="clearSelectedCustomer"
                                    type="button"
                                    class="px-3 py-1.5 min-h-[36px] rounded-xl text-xs font-bold text-[#1E1545] bg-white border border-[#E9E7F0] hover:bg-[#F4F3F8] active:scale-95 transition shrink-0 cursor-pointer"
                                >
                                    Cambiar
                                </button>
                            </div>

                            <!-- ALERTA SI TIENE PEDIDO ACTIVO -->
                            <div
                                v-if="hasActiveOrder"
                                class="p-3.5 rounded-xl bg-[#FEF5E6] border border-[#F5DFB5] text-[#9A670E] text-xs space-y-1.5"
                            >
                                <div class="flex items-center gap-2 font-extrabold">
                                    <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                                    <span>El cliente ya cuenta con un pedido activo</span>
                                </div>
                                <p class="leading-relaxed">
                                    Pedido <strong>{{ selectedCustomer.active_order.folio }}</strong> en estado <strong>{{ selectedCustomer.active_order.status_label }}</strong>.
                                    Cada cliente solo puede tener un pedido activo a la vez.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- MODO B: ALTA RÁPIDA -->
                    <div v-else class="space-y-3 text-xs">
                        <div>
                            <label class="block font-bold text-[#292638] mb-1">
                                Apodo del cliente <span class="text-[#E82147]">*</span>
                            </label>
                            <input
                                v-model="newCustomer.nickname"
                                type="text"
                                placeholder="Ej. Tienda Doña Mary, Taller Mecánico..."
                                class="w-full text-base sm:text-sm py-2.5 px-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] focus:border-[#1E1545] focus:ring-1 focus:ring-[#1E1545]"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block font-bold text-[#292638] mb-1">Nombre (opcional)</label>
                                <input
                                    v-model="newCustomer.name"
                                    type="text"
                                    placeholder="Nombre completo"
                                    class="w-full text-base sm:text-xs py-2.5 px-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] focus:border-[#1E1545] focus:ring-1 focus:ring-[#1E1545]"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-[#292638] mb-1">Teléfono (opcional)</label>
                                <input
                                    v-model="newCustomer.phone"
                                    type="text"
                                    placeholder="10 dígitos"
                                    class="w-full text-base sm:text-xs py-2.5 px-3 rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] focus:border-[#1E1545] focus:ring-1 focus:ring-[#1E1545]"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-[#292638] mb-1.5">
                                Zona de reparto <span class="text-[#E82147]">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button
                                    v-for="zone in availableZones"
                                    :key="zone.id"
                                    type="button"
                                    @click="newCustomer.delivery_zone = zone.id"
                                    class="py-2.5 px-3 rounded-xl text-xs font-bold border transition flex items-center justify-center gap-1.5 min-h-[44px] select-none cursor-pointer"
                                    :class="newCustomer.delivery_zone === zone.id
                                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                        : 'bg-[#F4F3F8]/60 text-[#292638] border-[#E9E7F0] hover:bg-[#F4F3F8]'"
                                >
                                    <MapPin class="w-3.5 h-3.5" :class="newCustomer.delivery_zone === zone.id ? 'text-white' : 'text-[#747181]'" aria-hidden="true" />
                                    <span>{{ zone.label }}</span>
                                </button>
                            </div>
                        </div>

                        <p class="text-[11px] text-[#747181]">
                            No se exige correo, contraseña ni domicilio detallado para registrarlo en el momento.
                        </p>
                    </div>
                </section>

                <!-- ========================================================================= -->
                <!-- 2. SERVICIO Y CANTIDAD                                                    -->
                <!-- ========================================================================= -->
                <div
                    v-if="clientMode === 'search' && !selectedCustomer"
                    class="bg-white rounded-2xl p-6 border border-[#E9E7F0] shadow-sm text-center space-y-2"
                >
                    <div class="w-11 h-11 rounded-2xl bg-[#F4F3F8] text-[#747181] mx-auto flex items-center justify-center">
                        <UserRound class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <p class="text-xs font-bold text-[#747181]">
                        Busca un cliente o elige "Nuevo rápido" para continuar con la entrega.
                    </p>
                </div>

                <div v-else class="space-y-4">

                    <!-- Selector de servicio (si el cliente es nuevo o sin asignación) -->
                    <section
                        v-if="isCustomerUnassigned"
                        class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-3"
                    >
                        <div>
                            <h2 class="text-sm sm:text-base font-extrabold text-[#1E1545]">2. Tipo de servicio</h2>
                            <p class="text-xs text-[#747181]">Define la modalidad de botellones para esta atención</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="selectService('fijos')"
                                class="p-3.5 rounded-2xl border-2 text-left transition flex items-center gap-3 relative cursor-pointer"
                                :class="selectedService === 'fijos'
                                    ? 'border-[#1E1545] bg-[#EEEAF7]/60'
                                    : 'border-[#E9E7F0] bg-white hover:bg-[#F4F3F8]/60'"
                            >
                                <div
                                    class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                                    :class="selectedService === 'fijos' ? 'bg-[#1E1545] text-white' : 'bg-[#F4F3F8] text-[#747181]'"
                                >
                                    <Package class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0 pr-6">
                                    <div class="text-sm font-extrabold text-[#1E1545]">Fijos</div>
                                    <p class="text-xs text-[#747181] mt-0.5">Recoger vacíos para Planta</p>
                                </div>
                                <span
                                    v-if="selectedService === 'fijos'"
                                    class="absolute top-3 right-3 w-5 h-5 rounded-full bg-[#1E1545] text-white flex items-center justify-center"
                                >
                                    <Check class="w-3 h-3" />
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="selectService('cambios')"
                                class="p-3.5 rounded-2xl border-2 text-left transition flex items-center gap-3 relative cursor-pointer"
                                :class="selectedService === 'cambios'
                                    ? 'border-[#0071BA] bg-[#E9F3F8]/70'
                                    : 'border-[#E9E7F0] bg-white hover:bg-[#F4F3F8]/60'"
                            >
                                <div
                                    class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                                    :class="selectedService === 'cambios' ? 'bg-[#0071BA] text-white' : 'bg-[#E9F3F8] text-[#0071BA]'"
                                >
                                    <RefreshCw class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0 pr-6">
                                    <div class="text-sm font-extrabold text-[#1E1545]">Cambios</div>
                                    <p class="text-xs text-[#747181] mt-0.5">Intercambio lleno por vacío</p>
                                </div>
                                <span
                                    v-if="selectedService === 'cambios'"
                                    class="absolute top-3 right-3 w-5 h-5 rounded-full bg-[#0071BA] text-white flex items-center justify-center"
                                >
                                    <Check class="w-3 h-3" />
                                </span>
                            </button>
                        </div>
                    </section>

                    <!-- Banner de modalidad exclusiva cuando el cliente ya tiene asignados -->
                    <section
                        v-else-if="isCustomerLocked"
                        class="bg-white rounded-2xl p-4 border border-[#E9E7F0] shadow-sm flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                :class="selectedService === 'fijos' ? 'bg-[#EEEAF7] text-[#1E1545]' : 'bg-[#E9F3F8] text-[#0071BA]'"
                            >
                                <Package v-if="selectedService === 'fijos'" class="w-5 h-5" aria-hidden="true" />
                                <RefreshCw v-else class="w-5 h-5" aria-hidden="true" />
                            </div>
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#747181] block">
                                    Modalidad asignada
                                </span>
                                <span class="text-sm font-extrabold text-[#1E1545]">
                                    {{ selectedService === 'fijos' ? 'Botellones Fijos' : 'Botellones de Cambio (20 L)' }}
                                </span>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-[#1E1545] bg-[#F4F3F8] border border-[#E9E7F0] px-3 py-1 rounded-full tabular-nums">
                            {{ selectedCustomer.assigned_bottles_count }} reg.
                        </span>
                    </section>

                    <!-- CASO A: FIJOS -->
                    <section
                        v-if="selectedService === 'fijos'"
                        class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-4"
                    >
                        <div class="flex items-center justify-between border-b border-[#E9E7F0] pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#EEEAF7] text-[#1E1545] flex items-center justify-center shrink-0">
                                    <Package class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div>
                                    <h2 class="text-sm sm:text-base font-extrabold text-[#1E1545]">Botellones Fijos (20 L)</h2>
                                    <p class="text-[11px] text-[#747181]">¿Cuántos fijos vacíos recogiste?</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 py-1">
                            <div class="flex items-center justify-center gap-4">
                                <button
                                    type="button"
                                    @click="decrementQty"
                                    :disabled="quantity <= 1"
                                    class="w-12 h-12 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] border border-[#E9E7F0] text-[#1E1545] font-black disabled:opacity-35 transition flex items-center justify-center cursor-pointer"
                                    aria-label="Restar botellón fijo"
                                >
                                    <Minus class="w-5 h-5" />
                                </button>

                                <div class="text-center">
                                    <input
                                        :value="quantity"
                                        @input="setQuantityValue($event.target.value)"
                                        type="number"
                                        min="1"
                                        class="w-24 text-center text-2xl font-black text-[#1E1545] bg-[#F4F3F8]/60 border border-[#E9E7F0] rounded-xl py-2 tabular-nums focus:border-[#1E1545]"
                                    />
                                    <span class="block text-[11px] font-bold text-[#747181] mt-1">
                                        {{ quantity === 1 ? 'Botellón fijo' : 'Botellones fijos' }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="incrementQty"
                                    class="w-12 h-12 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-black transition flex items-center justify-center cursor-pointer"
                                    aria-label="Sumar botellón fijo"
                                >
                                    <Plus class="w-5 h-5" />
                                </button>
                            </div>

                            <p class="text-[11px] text-center text-[#747181]">
                                Se envían directo a <strong>Planta → Por recibir</strong> (sin requerir cambios llenos en camión).
                            </p>
                        </div>

                        <div class="p-3.5 rounded-xl bg-[#EEEAF7] border border-[#D7CEF0] text-center font-bold text-[#1E1545] text-xs sm:text-sm">
                            Recoges {{ quantity }} {{ quantity === 1 ? 'fijo' : 'fijos' }} para llevar a Planta
                        </div>

                        <button
                            type="button"
                            @click="submitDirectOrder"
                            :disabled="form.processing || hasActiveOrder || (clientMode === 'search' && !selectedCustomer) || (clientMode === 'new' && !newCustomer.nickname.trim())"
                            class="w-full py-4 min-h-[54px] rounded-2xl text-base font-extrabold bg-[#1E1545] hover:bg-[#2B1F60] text-white active:scale-[0.99] transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-45 cursor-pointer"
                        >
                            <Check class="w-5 h-5" aria-hidden="true" />
                            <span>{{ form.processing ? 'Guardando...' : `Registrar recogida de ${quantity} ${quantity === 1 ? 'fijo' : 'fijos'}` }}</span>
                        </button>
                    </section>

                    <!-- CASO B: CAMBIOS (DISEÑO UNIFICADO CON SERVESTOP) -->
                    <section
                        v-if="selectedService === 'cambios'"
                        class="bg-white rounded-2xl p-4 sm:p-5 border border-[#E9E7F0] shadow-sm space-y-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-[#E9E7F0] pb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                                    <RefreshCw class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div>
                                    <h2 class="font-extrabold text-[#1E1545] text-sm sm:text-base">
                                        Botellones de Cambio (20 L)
                                    </h2>
                                    <p class="text-[11px] text-[#747181]">
                                        Intercambio lleno por vacío en domicilio
                                    </p>
                                </div>
                            </div>

                            <span
                                class="px-3 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1 tabular-nums"
                                :class="truck.exchange_full_available > 0 ? 'bg-[#E8F2EC] text-[#26845B]' : 'bg-[#FCECEF] text-[#E82147]'"
                            >
                                <Truck class="w-3.5 h-3.5" aria-hidden="true" />
                                <span>{{ truck.exchange_full_available }} llenos en camión</span>
                            </span>
                        </div>

                        <!-- Aviso si camión tiene 0 cambios llenos -->
                        <div
                            v-if="truck.exchange_full_available <= 0"
                            class="p-4 rounded-xl bg-[#FEF5E6] border border-[#F5DFB5] text-[#9A670E] text-xs space-y-1.5"
                        >
                            <div class="flex items-center gap-2 font-extrabold text-sm">
                                <AlertTriangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                                <span>No tienes cambios llenos disponibles</span>
                            </div>
                            <p class="leading-relaxed">
                                Tu camión no cuenta con botellones de cambio llenos para entregar en este momento.
                            </p>
                        </div>

                        <!-- Stepper táctil y resumen unificado -->
                        <div v-else class="space-y-4">
                            <div class="p-4 rounded-xl bg-[#F4F3F8]/70 border border-[#E9E7F0] space-y-2.5">
                                <label class="block text-center text-xs font-bold text-[#292638]">
                                    Cantidad de cambios a realizar
                                </label>

                                <div class="flex items-center justify-center gap-3.5">
                                    <button
                                        type="button"
                                        @click="decrementQty"
                                        :disabled="quantity <= 1"
                                        class="w-12 h-12 rounded-xl bg-white hover:bg-[#F4F3F8] border border-[#E9E7F0] text-[#1E1545] font-black disabled:opacity-35 transition flex items-center justify-center cursor-pointer"
                                        aria-label="Restar cambio"
                                    >
                                        <Minus class="w-5 h-5" />
                                    </button>

                                    <div class="text-center">
                                        <input
                                            :value="quantity"
                                            @input="setQuantityValue($event.target.value)"
                                            type="number"
                                            min="1"
                                            :max="truck.exchange_full_available"
                                            class="w-24 text-center text-2xl font-black text-[#1E1545] bg-white border border-[#E9E7F0] rounded-xl py-2 tabular-nums focus:border-[#1E1545]"
                                        />
                                        <span class="block text-[11px] font-bold text-[#747181] mt-1">
                                            {{ quantity === 1 ? 'Cambio' : 'Cambios' }}
                                        </span>
                                    </div>

                                    <button
                                        type="button"
                                        @click="incrementQty"
                                        :disabled="quantity >= truck.exchange_full_available"
                                        class="w-12 h-12 rounded-xl bg-[#1E1545] hover:bg-[#2B1F60] text-white font-black disabled:opacity-35 transition flex items-center justify-center cursor-pointer"
                                        aria-label="Sumar cambio"
                                    >
                                        <Plus class="w-5 h-5" />
                                    </button>
                                </div>

                                <p class="text-[11px] text-center text-[#747181]">
                                    Máximo disponible en camión: {{ truck.exchange_full_available }} unidades
                                </p>
                            </div>

                            <!-- Resumen de entrega y cobro unificado -->
                            <div class="p-3.5 rounded-xl bg-[#E9F3F8]/70 border border-[#CFE4F2] space-y-1.5 text-xs">
                                <div class="font-bold text-[#1E1545] text-center sm:text-left">
                                    Entregas {{ quantity }} {{ quantity === 1 ? 'lleno' : 'llenos' }} · Recibes {{ quantity }} {{ quantity === 1 ? 'vacío' : 'vacíos' }}
                                </div>
                                <div class="flex items-center justify-between pt-1.5 border-t border-[#CFE4F2] text-sm font-extrabold text-[#1E1545]">
                                    <span>Total ({{ quantity }} × {{ formatCurrency(unit_price) }})</span>
                                    <span class="text-base font-black text-[#0071BA] tabular-nums">{{ formatCurrency(exchangeTotalAmount) }} MXN</span>
                                </div>
                            </div>

                            <!-- Botón de confirmación unificado (esmeralda Puritronic) -->
                            <button
                                type="button"
                                @click="submitDirectOrder"
                                :disabled="form.processing || hasActiveOrder || (clientMode === 'search' && !selectedCustomer) || (clientMode === 'new' && !newCustomer.nickname.trim())"
                                class="w-full py-4 min-h-[54px] rounded-2xl text-base font-extrabold bg-[#26845B] hover:bg-[#1F6D4B] text-white active:scale-[0.99] transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-45 cursor-pointer"
                            >
                                <Check class="w-5 h-5" aria-hidden="true" />
                                <span>{{ form.processing ? 'Guardando...' : `Confirmar ${quantity} ${quantity === 1 ? 'cambio' : 'cambios'}` }}</span>
                            </button>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
