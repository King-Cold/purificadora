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

const hasAnyBlock = computed(() => {
    return Boolean(
        props.blocks.has_active_order ||
        props.blocks.outside_schedule ||
        props.blocks.no_address ||
        props.blocks.no_bottles ||
        props.blocks.no_customer
    );
});

const blockingReasonText = computed(() => {
    if (props.blocks.has_active_order) return 'Tienes un pedido activo en curso.';
    if (props.blocks.outside_schedule) return 'Recepción no disponible por horario.';
    if (props.blocks.no_address) return 'Falta registrar tu domicilio de entrega.';
    if (props.blocks.no_bottles) return 'Sin botellones de 20L en tu inventario.';
    if (props.blocks.no_customer) return 'Cuenta sin cliente vinculado.';
    if (totalSelected.value <= 0) return 'Selecciona al menos 1 botellón para continuar.';
    return '';
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
    <Head title="Nuevo Pedido - Puritronic" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Navegación de regreso y subtítulo breve -->
                <div class="flex items-center justify-between">
                    <Link
                        :href="route('cliente.dashboard')"
                        class="inline-flex items-center gap-2 text-xs font-medium text-slate-500 hover:text-blue-900 transition focus:outline-none"
                    >
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Volver al inicio</span>
                    </Link>
                    <span class="text-xs text-slate-400">Botellones de 20 litros a domicilio</span>
                </div>

                <!-- 2. AVISOS COMPACTOS DE BLOQUEO (SI EXISTE ALGUNA CONDICIÓN) -->
                <div
                    v-if="hasAnyBlock"
                    class="p-3.5 sm:p-4 rounded-xl bg-amber-50/90 border border-amber-200/90 text-amber-950 text-xs sm:text-sm space-y-2.5"
                >
                    <!-- Pedido activo -->
                    <div v-if="blocks.has_active_order && activeOrder" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-clock-rotate-left text-amber-700 mt-0.5 shrink-0"></i>
                            <span>
                                Tienes un pedido activo en curso (<strong>{{ activeOrder.folio }}</strong> - {{ activeOrder.status_label }}). Solo puedes tener un pedido activo a la vez.
                            </span>
                        </div>
                        <Link
                            :href="route('cliente.orders.show', activeOrder.id)"
                            class="font-semibold underline text-amber-900 hover:text-amber-950 shrink-0 text-xs ml-6 sm:ml-0"
                        >
                            Ver pedido activo
                        </Link>
                    </div>

                    <!-- Fuera de horario -->
                    <div v-if="blocks.outside_schedule" class="flex items-start gap-2">
                        <i class="fa-solid fa-store-slash text-amber-700 mt-0.5 shrink-0"></i>
                        <span>
                            <strong>Fuera de horario de recepción:</strong> {{ scheduleStatus.message }}.
                            Horarios: {{ scheduleSummary.order_intervals?.join(' y ') }} ({{ scheduleSummary.days_applied }}).
                        </span>
                    </div>

                    <!-- Sin domicilio -->
                    <div v-if="blocks.no_address" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot text-amber-700 mt-0.5 shrink-0"></i>
                            <span>Aún no tienes registrado tu domicilio de entrega en Hocabá.</span>
                        </div>
                        <Link
                            :href="route('cliente.addresses.create')"
                            class="font-semibold underline text-amber-900 hover:text-amber-950 shrink-0 text-xs ml-6 sm:ml-0"
                        >
                            Registrar domicilio
                        </Link>
                    </div>

                    <!-- Sin botellones -->
                    <div v-if="blocks.no_bottles" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-bottle-water text-amber-700 mt-0.5 shrink-0"></i>
                            <span>No tienes botellones de 20L registrados en tu cuenta para solicitar pedidos.</span>
                        </div>
                        <Link
                            :href="route('profile.edit')"
                            class="font-semibold underline text-amber-900 hover:text-amber-950 shrink-0 text-xs ml-6 sm:ml-0"
                        >
                            Indicar botellones
                        </Link>
                    </div>

                    <!-- Sin customer -->
                    <div v-if="blocks.no_customer" class="flex items-start gap-2">
                        <i class="fa-solid fa-user-xmark text-amber-700 mt-0.5 shrink-0"></i>
                        <span>Tu cuenta de usuario no tiene un perfil de cliente asociado. Contacta al administrador.</span>
                    </div>
                </div>

                <!-- Errores de validación backend -->
                <div
                    v-if="form.errors.schedule || form.errors.active_order || form.errors.address || form.errors.customer || form.errors.quantity"
                    class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1"
                >
                    <p v-if="form.errors.schedule" class="font-medium">{{ form.errors.schedule }}</p>
                    <p v-if="form.errors.active_order" class="font-medium">{{ form.errors.active_order }}</p>
                    <p v-if="form.errors.address" class="font-medium">{{ form.errors.address }}</p>
                    <p v-if="form.errors.customer" class="font-medium">{{ form.errors.customer }}</p>
                    <p v-if="form.errors.quantity" class="font-medium">{{ form.errors.quantity }}</p>
                </div>

                <!-- 3. ESTRUCTURA PRINCIPAL EN 2 COLUMNAS (FORMULARIO A LA IZQUIERDA, RESUMEN A LA DERECHA) -->
                <form @submit.prevent="submitOrder">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                        <!-- COLUMNA IZQUIERDA: FORMULARIO -->
                        <div class="lg:col-span-7 space-y-5">

                            <!-- TARJETA: ¿CUÁNTOS NECESITAS? -->
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-4">
                                <div class="border-b border-slate-100 pb-3">
                                    <h2 class="text-base font-semibold text-slate-900">
                                        ¿Cuántos necesitas?
                                    </h2>
                                </div>

                                <!-- Lista de Modalidades de 20L -->
                                <div class="space-y-3.5">

                                    <!-- 1. Botellones Fijos -->
                                    <div class="p-4 rounded-xl border border-slate-200/90 bg-slate-50/40">
                                        <!-- Si tiene botellones registrados -->
                                        <div v-if="registeredFixed > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-semibold text-slate-900">Fijos</span>
                                                    <span class="text-xs text-slate-500">({{ registeredFixed }} registrados)</span>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="setAllFixed"
                                                    :disabled="!props.canOrder || form.fixed_quantity === registeredFixed"
                                                    class="text-xs font-medium text-blue-900 hover:text-blue-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer underline"
                                                >
                                                    Pedir todos ({{ registeredFixed }})
                                                </button>
                                            </div>

                                            <!-- Control Stepper: Botones táctiles de 44px -->
                                            <div class="flex items-center gap-1.5 self-start sm:self-center">
                                                <button
                                                    type="button"
                                                    @click="decrementFixed"
                                                    :disabled="form.fixed_quantity <= 0 || !props.canOrder"
                                                    class="h-11 w-11 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center transition disabled:opacity-40 disabled:hover:bg-white disabled:cursor-not-allowed cursor-pointer focus:ring-2 focus:ring-blue-900/20"
                                                    title="Disminuir fijos"
                                                    aria-label="Disminuir fijos"
                                                >
                                                    <i class="fa-solid fa-minus text-xs"></i>
                                                </button>

                                                <input
                                                    type="number"
                                                    min="0"
                                                    :max="registeredFixed"
                                                    v-model.number="form.fixed_quantity"
                                                    :disabled="!props.canOrder"
                                                    class="h-11 w-14 text-center font-mono font-semibold text-base text-slate-900 border border-slate-200 rounded-xl bg-white focus:border-blue-900 focus:ring-2 focus:ring-blue-900/20"
                                                    aria-label="Cantidad de botellones fijos"
                                                />

                                                <button
                                                    type="button"
                                                    @click="incrementFixed"
                                                    :disabled="form.fixed_quantity >= registeredFixed || !props.canOrder"
                                                    class="h-11 w-11 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center transition disabled:opacity-40 disabled:hover:bg-white disabled:cursor-not-allowed cursor-pointer focus:ring-2 focus:ring-blue-900/20"
                                                    title="Aumentar fijos"
                                                    aria-label="Aumentar fijos"
                                                >
                                                    <i class="fa-solid fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Si tiene 0 registrados: fila compacta legible -->
                                        <div v-else class="flex items-center justify-between py-1">
                                            <span class="text-sm font-semibold text-slate-700">Fijos</span>
                                            <span class="text-xs text-slate-500 font-normal">Sin botellones registrados</span>
                                        </div>

                                        <InputError :message="form.errors.fixed_quantity" class="mt-1" />
                                    </div>

                                    <!-- 2. Botellones de Cambio -->
                                    <div class="p-4 rounded-xl border border-slate-200/90 bg-slate-50/40">
                                        <!-- Si tiene botellones registrados -->
                                        <div v-if="registeredExchange > 0" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-semibold text-slate-900">De cambio</span>
                                                    <span class="text-xs text-slate-500">({{ registeredExchange }} registrados)</span>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="setAllExchange"
                                                    :disabled="!props.canOrder || form.exchange_quantity === registeredExchange"
                                                    class="text-xs font-medium text-blue-900 hover:text-blue-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer underline"
                                                >
                                                    Pedir todos ({{ registeredExchange }})
                                                </button>
                                            </div>

                                            <!-- Control Stepper: Botones táctiles de 44px -->
                                            <div class="flex items-center gap-1.5 self-start sm:self-center">
                                                <button
                                                    type="button"
                                                    @click="decrementExchange"
                                                    :disabled="form.exchange_quantity <= 0 || !props.canOrder"
                                                    class="h-11 w-11 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center transition disabled:opacity-40 disabled:hover:bg-white disabled:cursor-not-allowed cursor-pointer focus:ring-2 focus:ring-blue-900/20"
                                                    title="Disminuir de cambio"
                                                    aria-label="Disminuir de cambio"
                                                >
                                                    <i class="fa-solid fa-minus text-xs"></i>
                                                </button>

                                                <input
                                                    type="number"
                                                    min="0"
                                                    :max="registeredExchange"
                                                    v-model.number="form.exchange_quantity"
                                                    :disabled="!props.canOrder"
                                                    class="h-11 w-14 text-center font-mono font-semibold text-base text-slate-900 border border-slate-200 rounded-xl bg-white focus:border-blue-900 focus:ring-2 focus:ring-blue-900/20"
                                                    aria-label="Cantidad de botellones de cambio"
                                                />

                                                <button
                                                    type="button"
                                                    @click="incrementExchange"
                                                    :disabled="form.exchange_quantity >= registeredExchange || !props.canOrder"
                                                    class="h-11 w-11 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center transition disabled:opacity-40 disabled:hover:bg-white disabled:cursor-not-allowed cursor-pointer focus:ring-2 focus:ring-blue-900/20"
                                                    title="Aumentar de cambio"
                                                    aria-label="Aumentar de cambio"
                                                >
                                                    <i class="fa-solid fa-plus text-xs"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Si tiene 0 registrados: fila compacta legible -->
                                        <div v-else class="flex items-center justify-between py-1">
                                            <span class="text-sm font-semibold text-slate-700">De cambio</span>
                                            <span class="text-xs text-slate-500 font-normal">Sin botellones registrados</span>
                                        </div>

                                        <InputError :message="form.errors.exchange_quantity" class="mt-1" />
                                    </div>

                                </div>

                                <!-- Ayuda desplegable accesible -->
                                <details class="group pt-2 border-t border-slate-100 text-xs">
                                    <summary class="cursor-pointer font-medium text-slate-500 hover:text-slate-800 flex items-center justify-between list-none select-none py-1">
                                        <span class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-circle-question text-slate-400"></i>
                                            <span>¿Cuál es la diferencia?</span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 group-open:rotate-180 transition-transform"></i>
                                    </summary>
                                    <div class="mt-2 space-y-1.5 text-[11px] text-slate-600 bg-slate-50/80 p-2.5 rounded-xl border border-slate-100 leading-relaxed">
                                        <p>
                                            <strong class="text-slate-800 font-semibold">Fijos:</strong> Envases marcados con tu nombre; se recogen vacíos, se lavan y rellenan en planta y se te regresan los mismos.
                                        </p>
                                        <p>
                                            <strong class="text-slate-800 font-semibold">De cambio:</strong> Envases comerciales estándar que intercambias vacíos por llenos en el momento de la entrega.
                                        </p>
                                    </div>
                                </details>
                            </div>

                            <!-- TARJETA: INDICACIONES DE ENTREGA (OPCIONAL) -->
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-2">
                                <div class="flex items-center justify-between">
                                    <label for="order-notes" class="text-sm font-semibold text-slate-900">
                                        Indicaciones de entrega <span class="text-xs font-normal text-slate-400">(opcional)</span>
                                    </label>
                                    <span class="text-[11px] font-mono text-slate-400">
                                        {{ form.notes?.length || 0 }} / 500
                                    </span>
                                </div>

                                <textarea
                                    id="order-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="500"
                                    :disabled="!props.canOrder"
                                    placeholder="Ej. Tocar el timbre"
                                    class="w-full rounded-xl border-slate-200 focus:border-blue-900 focus:ring-2 focus:ring-blue-900/20 text-xs sm:text-sm text-slate-800 placeholder:text-slate-400 resize-none transition"
                                ></textarea>
                                <InputError :message="form.errors.notes" />
                            </div>

                        </div>

                        <!-- COLUMNA DERECHA: RESUMEN COMPACTO -->
                        <div class="lg:col-span-5 space-y-4 lg:sticky lg:top-24">
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs space-y-4">
                                <div class="border-b border-slate-100 pb-3">
                                    <h2 class="text-sm font-semibold text-slate-900">
                                        Resumen del pedido
                                    </h2>
                                </div>

                                <!-- Vista previa del domicilio de entrega actual -->
                                <div class="space-y-1">
                                    <span class="text-[11px] font-medium text-slate-500 block">
                                        Domicilio de entrega (vista previa)
                                    </span>
                                    <template v-if="address">
                                        <p class="text-xs sm:text-sm font-semibold text-slate-900 leading-snug">
                                            {{ address.full_address }}
                                        </p>
                                        <p v-if="address.between_streets" class="text-[11px] text-slate-500">
                                            <span class="text-slate-400">Entre calles:</span> {{ address.between_streets }}
                                        </p>
                                        <p v-if="address.reference" class="text-[11px] text-slate-500">
                                            <span class="text-slate-400">Referencia:</span> {{ address.reference }}
                                        </p>
                                    </template>
                                    <template v-else>
                                        <p class="text-xs text-amber-700 font-medium">
                                            Sin domicilio registrado.
                                        </p>
                                    </template>
                                </div>

                                <!-- Desglose de cantidades (omitiendo modalidades en cero) -->
                                <div class="pt-3 border-t border-slate-100 space-y-2 text-xs">
                                    <div v-if="form.fixed_quantity > 0" class="flex items-center justify-between text-slate-700">
                                        <span>Fijos</span>
                                        <span class="font-mono font-semibold">{{ form.fixed_quantity }}</span>
                                    </div>
                                    <div v-if="form.exchange_quantity > 0" class="flex items-center justify-between text-slate-700">
                                        <span>De cambio</span>
                                        <span class="font-mono font-semibold">{{ form.exchange_quantity }}</span>
                                    </div>
                                    <div v-if="totalSelected === 0" class="text-[11px] text-slate-400 italic">
                                        Sin botellones seleccionados
                                    </div>
                                </div>

                                <!-- Total de botellones destacado en una sola línea -->
                                <div class="pt-3 border-t border-slate-200 flex items-center justify-between">
                                    <span class="text-xs sm:text-sm font-semibold text-slate-900">Total de botellones</span>
                                    <span class="text-base sm:text-lg font-bold font-mono text-blue-900">{{ totalSelected }}</span>
                                </div>

                                <!-- Botones de acción -->
                                <div class="pt-2 space-y-2">
                                    <button
                                        type="submit"
                                        :disabled="!canSubmit"
                                        class="w-full py-3 px-5 rounded-xl text-xs sm:text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-xs flex items-center justify-center gap-2 cursor-pointer focus:ring-2 focus:ring-blue-900/20"
                                    >
                                        <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
                                        <span>{{ form.processing ? 'Registrando...' : 'Solicitar pedido' }}</span>
                                    </button>

                                    <!-- Motivo breve del bloqueo cerca del botón -->
                                    <p v-if="!canSubmit && blockingReasonText" class="text-[11px] text-center text-slate-500 leading-tight">
                                        {{ blockingReasonText }}
                                    </p>

                                    <!-- Acción secundaria Volver -->
                                    <Link
                                        :href="route('cliente.dashboard')"
                                        class="block w-full py-2 text-center text-xs font-medium text-slate-500 hover:text-slate-800 transition cursor-pointer"
                                    >
                                        Volver
                                    </Link>
                                </div>

                            </div>
                        </div>

                    </div>
                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
