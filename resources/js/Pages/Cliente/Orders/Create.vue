<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        default: null,
    },
    address: {
        type: Object,
        default: null,
    },
    activeOrder: {
        type: Object,
        default: null,
    },
    scheduleStatus: {
        type: Object,
        required: true,
    },
    scheduleSummary: {
        type: Object,
        required: true,
    },
    blocks: {
        type: Object,
        required: true,
    },
    canOrder: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    fixed_quantity: 0,
    exchange_quantity: 0,
    notes: '',
});

const registeredFixed = computed(() => Number(props.customer?.fixed_bottles_count ?? 0));
const registeredExchange = computed(() => Number(props.customer?.exchange_bottles_count ?? 0));
const totalRegistered = computed(() => Number(props.customer?.total_bottles ?? 0));

const totalSelected = computed(() => {
    return Number(form.fixed_quantity || 0) + Number(form.exchange_quantity || 0);
});

const hasValidQuantity = computed(() => {
    return totalSelected.value > 0 &&
        Number(form.fixed_quantity || 0) <= registeredFixed.value &&
        Number(form.exchange_quantity || 0) <= registeredExchange.value;
});

const canSubmit = computed(() => {
    return props.canOrder && hasValidQuantity.value && !form.processing;
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

const setAllFixed = () => {
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

const setAllExchange = () => {
    form.exchange_quantity = registeredExchange.value;
};

const submitOrder = () => {
    if (!canSubmit.value) return;

    form.post(route('cliente.orders.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Nuevo Pedido a Domicilio - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <Link
                        :href="route('cliente.dashboard')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0"
                        title="Volver al panel"
                    >
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                                Nuevo Pedido a Domicilio
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900 border border-blue-200/80">
                                20 Litros
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Solicita botellones fijos o de cambio de 20L para entrega en tu domicilio
                        </p>
                    </div>
                </div>

                <div v-if="customer?.code" class="self-start sm:self-auto">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs">
                        <i class="fa-solid fa-barcode text-slate-400 text-xs"></i>
                        <span>{{ customer.code }}</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- ========================================================================= -->
                <!-- 1. ALERTAS DE BLOQUEO COMERCIAL (SI EXISTE ALGUNA CONDICIÓN LIMITANTE) -->
                <!-- ========================================================================= -->

                <!-- A. Pedido Activo Pendiente o Confirmado -->
                <div
                    v-if="blocks.has_active_order && activeOrder"
                    class="p-5 sm:p-6 rounded-2xl bg-amber-50/90 border border-amber-300/80 shadow-xs text-amber-950"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div class="flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h3 class="text-base font-bold text-amber-950">
                                    Ya tienes un pedido activo en proceso
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-mono font-bold bg-amber-200/80 text-amber-900 border border-amber-300">
                                    {{ activeOrder.folio }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                    Estado: {{ activeOrder.status_label }}
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-amber-900 leading-relaxed">
                                Cuentas con un pedido activo registrado el <strong>{{ activeOrder.created_at }}</strong> con un total de <strong>{{ activeOrder.total_bottles }} botellón(es) de 20L</strong>. Por regla de servicio, únicamente se admite un pedido activo a la vez por cliente.
                            </p>
                            <p class="text-xs text-amber-800 font-medium pt-1">
                                <i class="fa-solid fa-circle-info mr-1 text-[11px]"></i>
                                Podrás solicitar un nuevo pedido una vez que este pedido sea entregado por reparto o cancelado.
                            </p>
                            <div class="pt-2">
                                <Link
                                    :href="route('cliente.orders.show', activeOrder.id)"
                                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold text-amber-950 bg-amber-200/90 hover:bg-amber-300 border border-amber-300/80 transition shadow-2xs cursor-pointer"
                                >
                                    <i class="fa-regular fa-file-lines text-xs"></i>
                                    <span>Consultar pedido {{ activeOrder.folio }}</span>
                                    <i class="fa-solid fa-arrow-right text-[10px] ml-0.5"></i>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- B. Fuera de Horario de Recepción -->
                <div
                    v-if="blocks.outside_schedule"
                    class="p-5 sm:p-6 rounded-2xl bg-orange-50/90 border border-orange-300/80 shadow-xs text-orange-950"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-orange-500 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="fa-solid fa-store-slash"></i>
                        </div>
                        <div class="flex-1 space-y-1.5">
                            <h3 class="text-base font-bold text-orange-950">
                                Recepción de pedidos no disponible por horario
                            </h3>
                            <p class="text-xs sm:text-sm text-orange-900 leading-relaxed font-semibold">
                                {{ scheduleStatus.message }}
                            </p>
                            <div class="text-xs text-orange-800 pt-1 space-y-1">
                                <p>
                                    <span class="font-bold">Horarios de recepción de pedidos:</span>
                                    <span class="ml-1 font-mono font-semibold">{{ scheduleSummary.order_intervals?.join(' y ') }}</span>
                                    <span class="ml-1">({{ scheduleSummary.days_applied }})</span>.
                                </p>
                                <p class="text-orange-700">
                                    Hora límite de recepción: <strong>{{ scheduleSummary.order_cutoff_time }} hrs</strong> (con anticipación previa al cierre general de la sucursal).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- C. Sin Domicilio Activo Registrado -->
                <div
                    v-if="blocks.no_address"
                    class="p-5 sm:p-6 rounded-2xl bg-sky-50/90 border border-sky-300/80 shadow-xs text-sky-950"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-sky-600 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="flex-1 space-y-2">
                            <h3 class="text-base font-bold text-sky-950">
                                Domicilio de entrega no registrado
                            </h3>
                            <p class="text-xs sm:text-sm text-sky-900 leading-relaxed">
                                Para poder enviar tus botellones a domicilio necesitamos conocer tu dirección en Hocabá (calle, número y referencias).
                            </p>
                            <div class="pt-1">
                                <Link
                                    :href="route('cliente.addresses.create')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-sky-700 hover:bg-sky-800 transition shadow-xs cursor-pointer"
                                >
                                    <i class="fa-solid fa-house-chimney"></i>
                                    <span>Registrar mi domicilio</span>
                                    <i class="fa-solid fa-arrow-right text-[10px] ml-0.5"></i>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- D. Sin Botellones Registrados -->
                <div
                    v-if="blocks.no_bottles"
                    class="p-5 sm:p-6 rounded-2xl bg-slate-100 border border-slate-300 shadow-xs text-slate-800"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-slate-600 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="fa-solid fa-bottle-water"></i>
                        </div>
                        <div class="flex-1 space-y-1.5">
                            <h3 class="text-base font-bold text-slate-900">
                                Sin botellones de 20 litros asignados
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                                Tu cuenta de cliente actualmente tiene <strong>0 botellones fijos y 0 botellones de cambio</strong> registrados. Para realizar un pedido a domicilio necesitas contar con al menos un envase en tu inventario.
                            </p>
                            <p class="text-xs text-slate-500 pt-0.5">
                                Comunícate con la administración de Puritronic para dar de alta o asignar tus envases.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- E. Sin Ficha de Cliente -->
                <div
                    v-if="blocks.no_customer"
                    class="p-5 sm:p-6 rounded-2xl bg-rose-50 border border-rose-300 shadow-xs text-rose-950"
                >
                    <div class="flex items-start gap-4">
                        <div class="h-11 w-11 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                        <div class="flex-1 space-y-1">
                            <h3 class="text-base font-bold text-rose-950">
                                Cuenta de cliente no vinculada
                            </h3>
                            <p class="text-xs sm:text-sm text-rose-800 leading-relaxed">
                                Tu usuario aún no tiene un perfil de cliente asociado en la plataforma. Por favor contacta al administrador.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- 2. FORMULARIO PRINCIPAL DE PEDIDO A DOMICILIO -->
                <!-- ========================================================================= -->
                <form @submit.prevent="submitOrder" class="space-y-6">

                    <!-- Error general o de regla comercial -->
                    <div
                        v-if="form.errors.schedule || form.errors.active_order || form.errors.address || form.errors.customer || form.errors.quantity"
                        class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs sm:text-sm space-y-1"
                    >
                        <p v-if="form.errors.schedule" class="font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            {{ form.errors.schedule }}
                        </p>
                        <p v-if="form.errors.active_order" class="font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            {{ form.errors.active_order }}
                        </p>
                        <p v-if="form.errors.address" class="font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            {{ form.errors.address }}
                        </p>
                        <p v-if="form.errors.customer" class="font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            {{ form.errors.customer }}
                        </p>
                        <p v-if="form.errors.quantity" class="font-medium flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                            {{ form.errors.quantity }}
                        </p>
                    </div>

                    <!-- TARJETA: SELECCIÓN DE BOTELLONES DE 20L -->
                    <div class="bg-white rounded-2xl p-5 sm:p-7 shadow-xs border border-slate-200/80 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fa-solid fa-bottle-water text-blue-900"></i>
                                    <span>Selecciona tus Botellones de 20 Litros</span>
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Los pedidos a domicilio son exclusivamente de 20 litros. Puedes solicitar fijos, de cambio o ambos.
                                </p>
                            </div>
                            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                                Capacidad: 20L
                            </span>
                        </div>

                        <!-- Cuadrícula con las 2 modalidades de 20L -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            <!-- 1. Botellones Fijos (20 Litros) -->
                            <div
                                :class="[
                                    'rounded-2xl p-5 border transition-all duration-150 flex flex-col justify-between',
                                    registeredFixed > 0
                                        ? 'bg-blue-50/40 border-blue-200/80'
                                        : 'bg-slate-50/70 border-slate-200 opacity-60'
                                ]"
                            >
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-9 w-9 rounded-xl bg-blue-900 text-white flex items-center justify-center shrink-0 text-sm">
                                                <i class="fa-solid fa-water"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">
                                                    Botellones Fijos
                                                </h4>
                                                <span class="text-[11px] font-semibold text-blue-950">
                                                    20 Litros (Rotulados)
                                                </span>
                                            </div>
                                        </div>

                                        <span
                                            :class="[
                                                'text-xs font-bold px-2.5 py-1 rounded-xl font-mono',
                                                registeredFixed > 0
                                                    ? 'bg-blue-100 text-blue-900 border border-blue-200'
                                                    : 'bg-slate-200 text-slate-600'
                                            ]"
                                        >
                                            {{ registeredFixed }} reg.
                                        </span>
                                    </div>

                                    <p class="text-xs text-slate-500 mt-2.5 leading-relaxed">
                                        Tus propios envases de 20L; se recogen, lavan, rellenan y devuelven rotulados.
                                    </p>
                                </div>

                                <div class="mt-4 pt-3 border-t border-slate-200/60">
                                    <template v-if="registeredFixed > 0">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs font-semibold text-slate-700">
                                                Cantidad a pedir:
                                            </span>

                                            <!-- Selector Numérico con Botones +/- -->
                                            <div class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-slate-300 shadow-2xs">
                                                <button
                                                    type="button"
                                                    @click="decrementFixed"
                                                    :disabled="form.fixed_quantity <= 0 || !props.canOrder"
                                                    class="h-9 w-9 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-900 disabled:opacity-40 disabled:hover:bg-slate-100 disabled:hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm"
                                                    aria-label="Disminuir fijos"
                                                >
                                                    <i class="fa-solid fa-minus"></i>
                                                </button>

                                                <input
                                                    type="number"
                                                    min="0"
                                                    :max="registeredFixed"
                                                    v-model.number="form.fixed_quantity"
                                                    :disabled="!props.canOrder"
                                                    class="w-14 text-center font-mono font-bold text-base text-slate-900 border-0 focus:ring-0 p-0"
                                                />

                                                <button
                                                    type="button"
                                                    @click="incrementFixed"
                                                    :disabled="form.fixed_quantity >= registeredFixed || !props.canOrder"
                                                    class="h-9 w-9 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-700 hover:text-blue-900 disabled:opacity-40 disabled:hover:bg-slate-100 disabled:hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm"
                                                    aria-label="Aumentar fijos"
                                                >
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between mt-2 pt-1">
                                            <button
                                                type="button"
                                                @click="setAllFixed"
                                                :disabled="!props.canOrder || form.fixed_quantity === registeredFixed"
                                                class="text-[11px] font-semibold text-blue-900 hover:text-blue-700 underline disabled:opacity-40 disabled:no-underline cursor-pointer"
                                            >
                                                Pedir todos ({{ registeredFixed }})
                                            </button>
                                            <span class="text-[11px] text-slate-400">
                                                Máx. {{ registeredFixed }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="py-2 text-center text-xs text-slate-400">
                                            <i class="fa-solid fa-ban text-slate-400 mr-1"></i>
                                            Sin botellones fijos registrados
                                        </div>
                                    </template>

                                    <InputError :message="form.errors.fixed_quantity" class="mt-2" />
                                </div>
                            </div>

                            <!-- 2. Botellones de Cambio (20 Litros) -->
                            <div
                                :class="[
                                    'rounded-2xl p-5 border transition-all duration-150 flex flex-col justify-between',
                                    registeredExchange > 0
                                        ? 'bg-sky-50/40 border-sky-200/80'
                                        : 'bg-slate-50/70 border-slate-200 opacity-60'
                                ]"
                            >
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-9 w-9 rounded-xl bg-sky-700 text-white flex items-center justify-center shrink-0 text-sm">
                                                <i class="fa-solid fa-bottle-water"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">
                                                    Botellones de Cambio
                                                </h4>
                                                <span class="text-[11px] font-semibold text-sky-950">
                                                    20 Litros (Intercambio)
                                                </span>
                                            </div>
                                        </div>

                                        <span
                                            :class="[
                                                'text-xs font-bold px-2.5 py-1 rounded-xl font-mono',
                                                registeredExchange > 0
                                                    ? 'bg-sky-100 text-sky-900 border border-sky-200'
                                                    : 'bg-slate-200 text-slate-600'
                                            ]"
                                        >
                                            {{ registeredExchange }} reg.
                                        </span>
                                    </div>

                                    <p class="text-xs text-slate-500 mt-2.5 leading-relaxed">
                                        Envases estándar de 20L que intercambias vacíos por llenos al momento de la entrega.
                                    </p>
                                </div>

                                <div class="mt-4 pt-3 border-t border-slate-200/60">
                                    <template v-if="registeredExchange > 0">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="text-xs font-semibold text-slate-700">
                                                Cantidad a pedir:
                                            </span>

                                            <!-- Selector Numérico con Botones +/- -->
                                            <div class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-slate-300 shadow-2xs">
                                                <button
                                                    type="button"
                                                    @click="decrementExchange"
                                                    :disabled="form.exchange_quantity <= 0 || !props.canOrder"
                                                    class="h-9 w-9 rounded-lg bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-900 disabled:opacity-40 disabled:hover:bg-slate-100 disabled:hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm"
                                                    aria-label="Disminuir de cambio"
                                                >
                                                    <i class="fa-solid fa-minus"></i>
                                                </button>

                                                <input
                                                    type="number"
                                                    min="0"
                                                    :max="registeredExchange"
                                                    v-model.number="form.exchange_quantity"
                                                    :disabled="!props.canOrder"
                                                    class="w-14 text-center font-mono font-bold text-base text-slate-900 border-0 focus:ring-0 p-0"
                                                />

                                                <button
                                                    type="button"
                                                    @click="incrementExchange"
                                                    :disabled="form.exchange_quantity >= registeredExchange || !props.canOrder"
                                                    class="h-9 w-9 rounded-lg bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-900 disabled:opacity-40 disabled:hover:bg-slate-100 disabled:hover:text-slate-700 flex items-center justify-center transition cursor-pointer text-sm"
                                                    aria-label="Aumentar de cambio"
                                                >
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between mt-2 pt-1">
                                            <button
                                                type="button"
                                                @click="setAllExchange"
                                                :disabled="!props.canOrder || form.exchange_quantity === registeredExchange"
                                                class="text-[11px] font-semibold text-sky-900 hover:text-sky-700 underline disabled:opacity-40 disabled:no-underline cursor-pointer"
                                            >
                                                Pedir todos ({{ registeredExchange }})
                                            </button>
                                            <span class="text-[11px] text-slate-400">
                                                Máx. {{ registeredExchange }}
                                            </span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="py-2 text-center text-xs text-slate-400">
                                            <i class="fa-solid fa-ban text-slate-400 mr-1"></i>
                                            Sin botellones de cambio registrados
                                        </div>
                                    </template>

                                    <InputError :message="form.errors.exchange_quantity" class="mt-2" />
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- TARJETA: OBSERVACIONES OPCIONALES -->
                    <div class="bg-white rounded-2xl p-5 sm:p-7 shadow-xs border border-slate-200/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <label for="order-notes" class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i class="fa-regular fa-comment-dots text-slate-500"></i>
                                <span>Observaciones o instrucciones de entrega</span>
                                <span class="text-xs font-normal text-slate-400">(opcional)</span>
                            </label>
                            <span class="text-xs font-mono text-slate-400">
                                {{ form.notes?.length || 0 }} / 500
                            </span>
                        </div>

                        <textarea
                            id="order-notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="500"
                            :disabled="!props.canOrder"
                            placeholder="Ej. Tocar timbre blanco, dejar junto al portón azul o cualquier referencia para el repartidor..."
                            class="w-full rounded-xl border-slate-300 shadow-2xs focus:border-blue-900 focus:ring-blue-900 text-sm text-slate-800 placeholder:text-slate-400"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>

                    <!-- TARJETA: DOMICILIO DE ENTREGA Y RESUMEN FINAL -->
                    <div class="bg-white rounded-2xl p-5 sm:p-7 shadow-xs border border-slate-200/80 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                                <i class="fa-solid fa-clipboard-check text-blue-900"></i>
                                <span>Resumen de Entrega a Domicilio</span>
                            </h3>
                            <span v-if="address?.zone_label" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-900 border border-blue-200">
                                {{ address.zone_label }}
                            </span>
                        </div>

                        <!-- Dirección Activa -->
                        <div v-if="address" class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                            <div class="h-8 w-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fa-solid fa-location-dot text-sm"></i>
                            </div>
                            <div class="space-y-0.5">
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">
                                    Lugar de Entrega
                                </span>
                                <p class="text-sm font-bold text-slate-900">
                                    {{ address.full_address }}
                                </p>
                                <p v-if="address.reference" class="text-xs text-slate-500 pt-0.5">
                                    <span class="font-medium">Referencia:</span> {{ address.reference }}
                                </p>
                            </div>
                        </div>

                        <!-- Desglose de Cantidades -->
                        <div class="grid grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50/80 border border-slate-200/60 text-center">
                            <div>
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    Fijos (20L)
                                </span>
                                <span class="text-2xl font-extrabold text-blue-950 font-mono">
                                    {{ form.fixed_quantity }}
                                </span>
                            </div>
                            <div>
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    Cambio (20L)
                                </span>
                                <span class="text-2xl font-extrabold text-sky-800 font-mono">
                                    {{ form.exchange_quantity }}
                                </span>
                            </div>
                            <div class="border-l border-slate-200">
                                <span class="text-[11px] font-bold text-blue-900 uppercase tracking-wider block">
                                    Total a Llevar
                                </span>
                                <span class="text-2xl font-extrabold text-slate-900 font-mono">
                                    {{ totalSelected }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN DE CONFIRMACIÓN Y ENVÍO -->
                    <div class="space-y-3 pt-2">
                        <div class="flex flex-col sm:flex-row items-center justify-end gap-3">
                            <Link
                                :href="route('cliente.dashboard')"
                                class="w-full sm:w-auto px-5 py-3 rounded-xl text-center text-xs sm:text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="!canSubmit"
                                class="w-full sm:w-auto px-7 py-3 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-40 disabled:hover:bg-blue-900 disabled:cursor-not-allowed transition shadow-md flex items-center justify-center gap-2.5 cursor-pointer"
                            >
                                <i v-if="form.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                                <i v-else class="fa-solid fa-paper-plane text-sky-400"></i>
                                <span>{{ form.processing ? 'Registrando Pedido...' : 'Confirmar y Solicitar Pedido' }}</span>
                            </button>
                        </div>

                        <!-- Mensaje de ayuda contextual si el botón está inhabilitado -->
                        <p v-if="!props.canOrder" class="text-xs text-center text-slate-400">
                            El registro de pedidos se encuentra bloqueado temporalmente por las condiciones señaladas arriba.
                        </p>
                        <p v-else-if="totalSelected <= 0" class="text-xs text-center text-slate-500">
                            Selecciona al menos 1 botellón de 20 litros (fijo o de cambio) para habilitar el envío del pedido.
                        </p>
                    </div>

                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
