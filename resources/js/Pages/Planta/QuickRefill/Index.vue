<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    unitPrice: {
        type: Number,
        required: true,
    },
    capacity: {
        type: Number,
        default: 20,
    },
    todaySummary: {
        type: Object,
        default: () => ({
            bottles_refilled: 0,
            total_amount: 0,
            services_count: 0,
        }),
    },
    recentRefills: {
        type: Array,
        default: () => [],
    },
    isOperator: {
        type: Boolean,
        default: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// Formulario de un solo campo editable: cantidad
const form = useForm({
    quantity: 1,
    notes: '',
});

// Cálculo dinámico en tiempo real del importe
const currentTotal = computed(() => {
    const qty = Number(form.quantity) || 0;
    return (qty * props.unitPrice).toFixed(2);
});

// Controles táctiles de incremento y decremento (mínimo 1)
const increment = (delta = 1) => {
    const current = Number(form.quantity) || 1;
    form.quantity = Math.max(1, Math.min(999, current + delta));
};

const decrement = (delta = 1) => {
    const current = Number(form.quantity) || 1;
    form.quantity = Math.max(1, current - delta);
};

const setQuantity = (val) => {
    form.quantity = Math.max(1, Math.min(999, val));
};

const isSubmitting = ref(false);

const submitRefill = () => {
    if (form.processing || isSubmitting.value) return;
    if (form.quantity < 1) {
        form.quantity = 1;
    }

    isSubmitting.value = true;
    form.post(route('planta.quick-refill.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Deja listo el formulario para la siguiente atención restableciendo a 1
            form.reset('quantity', 'notes');
            form.quantity = 1;
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

// Control para eliminar registro por equivocación
const refillToDelete = ref(null);
const isDeleting = ref(false);

const confirmDeleteRefill = (item) => {
    refillToDelete.value = item;
};

const cancelDeleteRefill = () => {
    refillToDelete.value = null;
};

const deleteRefill = () => {
    if (!refillToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('planta.quick-refill.destroy', refillToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            refillToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Planta · Llenado Rápido" />

    <AuthenticatedLayout>
        <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Mensajes Flash de confirmación -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="flashSuccess"
                    class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-2xs"
                    role="alert"
                >
                    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold">{{ flashSuccess }}</p>
                        <p class="text-xs text-emerald-700">Listo para registrar la siguiente atención.</p>
                    </div>
                </div>
            </transition>

            <div
                v-if="flashError"
                class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-3 shadow-2xs"
                role="alert"
            >
                <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-bold">{{ flashError }}</p>
                </div>
            </div>

            <!-- MÓDULO PRINCIPAL: LLENADO RÁPIDO DE MOSTRADOR -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 sm:p-8 space-y-6">
                <!-- Encabezado de la tarea -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-slate-100">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-blue-900 text-white flex items-center justify-center shadow-2xs shrink-0">
                            <i class="fa-solid fa-bolt text-xl text-sky-400"></i>
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Llenado de mostrador
                            </h1>
                            <p class="text-xs sm:text-sm font-medium text-slate-500">
                                Clientes particulares en Planta · Garrafones exclusivos de 20L
                            </p>
                        </div>
                    </div>

                    <!-- Indicador del precio unitario vigente de Planta -->
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-slate-50 border border-slate-200/80 text-slate-700 self-start sm:self-auto">
                        <i class="fa-solid fa-tag text-blue-900 text-xs"></i>
                        <span class="text-xs font-semibold text-slate-500">Precio Planta:</span>
                        <span class="text-sm font-black text-slate-900">${{ Number(unitPrice).toFixed(2) }} MXN</span>
                    </div>
                </div>

                <!-- Formulario táctil simplificado (1 solo campo editable: cantidad) -->
                <form @submit.prevent="submitRefill" class="space-y-6">
                    <div class="flex flex-col items-center justify-center py-4 bg-slate-50/70 rounded-3xl border border-slate-100 p-6 space-y-5">
                        <label for="bottle-quantity" class="text-xs font-bold uppercase tracking-wider text-slate-500 text-center">
                            Cantidad de botellones llenados
                        </label>

                        <!-- Control numérico táctil de gran tamaño (≥ 44x44 px) -->
                        <div class="flex items-center justify-center gap-4 sm:gap-6 w-full max-w-md">
                            <!-- Botón Menos (-) -->
                            <button
                                type="button"
                                @click="decrement(1)"
                                :disabled="form.quantity <= 1 || form.processing"
                                class="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-100 active:scale-95 transition flex items-center justify-center text-xl sm:text-2xl font-black shadow-2xs disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-900/20"
                                aria-label="Restar un botellón"
                            >
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <!-- Campo numérico editable centrado -->
                            <div class="relative flex-1 max-w-[160px] text-center">
                                <input
                                    id="bottle-quantity"
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="1"
                                    max="999"
                                    step="1"
                                    required
                                    class="w-full text-center text-4xl sm:text-5xl font-black text-slate-900 bg-white border-2 border-blue-900/30 rounded-2xl py-3 px-2 focus:outline-none focus:ring-4 focus:ring-blue-900/20 focus:border-blue-900 shadow-inner"
                                />
                                <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1.5">
                                    Botellones (20L)
                                </span>
                            </div>

                            <!-- Botón Más (+) -->
                            <button
                                type="button"
                                @click="increment(1)"
                                :disabled="form.processing"
                                class="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition flex items-center justify-center text-xl sm:text-2xl font-black shadow-2xs cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-900/30"
                                aria-label="Sumar un botellón"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>

                        <!-- Botones de acceso rápido táctil -->
                        <div class="flex items-center gap-2 flex-wrap justify-center pt-1">
                            <span class="text-xs font-semibold text-slate-400 mr-1">Rápido:</span>
                            <button
                                v-for="n in [1, 2, 3, 4, 5, 10]"
                                :key="n"
                                type="button"
                                @click="setQuantity(n)"
                                :class="[
                                    'min-h-[44px] min-w-[44px] px-3.5 py-2 rounded-xl text-xs font-black transition cursor-pointer border',
                                    form.quantity === n
                                        ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                                ]"
                            >
                                {{ n }}
                            </button>
                        </div>

                        <!-- Desglose de precio y total en tiempo real: «3 × $15 = $45» -->
                        <div class="pt-3 text-center border-t border-slate-200/60 w-full max-w-sm">
                            <div class="text-sm font-semibold text-slate-500">
                                Importe del servicio:
                            </div>
                            <div class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5 tracking-tight">
                                <span>{{ form.quantity || 0 }}</span>
                                <span class="text-slate-400 mx-1.5">×</span>
                                <span>${{ Number(unitPrice).toFixed(2) }}</span>
                                <span class="text-slate-400 mx-1.5">=</span>
                                <span class="text-emerald-700">${{ currentTotal }} MXN</span>
                            </div>
                        </div>
                    </div>

                    <!-- Botón Principal de Acción Táctil (≥ 44x44 px) -->
                    <button
                        type="submit"
                        :disabled="form.processing || isSubmitting"
                        class="w-full min-h-[56px] rounded-2xl bg-blue-900 hover:bg-blue-800 text-white font-black text-base sm:text-lg flex items-center justify-center gap-3 shadow-md hover:shadow-lg active:scale-[0.99] transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="form.processing || isSubmitting" class="fa-solid fa-circle-notch fa-spin"></i>
                        <i v-else class="fa-solid fa-check text-sky-400"></i>
                        <span>Registrar {{ form.quantity }} llenado{{ form.quantity > 1 ? 's' : '' }} (${{ currentTotal }})</span>
                    </button>
                </form>
            </div>

            <!-- RESUMEN DE LA JORNADA EN MOSTRADOR Y ACTIVIDAD RECIENTE -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Métrica 1: Botellones de mostrador hoy -->
                <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-bottle-water text-lg"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Llenados hoy</span>
                            <span class="text-2xl font-black text-slate-900">{{ todaySummary.bottles_refilled }}</span>
                        </div>
                    </div>
                </div>

                <!-- Métrica 2: Total recaudado en mostrador hoy -->
                <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-money-bill-wave text-lg"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total mostrador hoy</span>
                            <span class="text-2xl font-black text-emerald-700">${{ Number(todaySummary.total_amount).toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Métrica 3: Clientes atendidos hoy -->
                <div class="bg-white p-5 rounded-3xl border border-slate-200/90 shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-sky-50 text-sky-900 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-users text-lg"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Atenciones registradas</span>
                            <span class="text-2xl font-black text-slate-900">{{ todaySummary.services_count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LISTA COMPACTA DE ATENCIONES DE HOY -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-slate-400 text-sm"></i>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                            Atenciones registradas hoy
                        </h2>
                    </div>
                    <span class="text-xs font-bold text-slate-400">
                        {{ recentRefills.length }} registro{{ recentRefills.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <!-- Estado vacío -->
                <div v-if="recentRefills.length === 0" class="py-8 text-center text-slate-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                    <p class="text-sm font-semibold">No se han registrado llenados de mostrador el día de hoy.</p>
                    <p class="text-xs text-slate-400">Usa el formulario superior para registrar el primer servicio.</p>
                </div>

                <!-- Lista compacta -->
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="item in recentRefills"
                        :key="item.id"
                        class="py-3 flex items-center justify-between gap-3 text-sm hover:bg-slate-50/50 rounded-xl px-2 transition"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold font-mono text-slate-400 w-12">{{ item.time }}</span>
                            <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center font-black text-xs">
                                {{ item.quantity }}
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-800">
                                    {{ item.quantity }} botellón{{ item.quantity > 1 ? 'es' : '' }} (20L)
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    Atendido por: {{ item.operator_name }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <span class="text-sm font-black text-slate-900">${{ Number(item.total_amount).toFixed(2) }} MXN</span>
                                <span class="block text-[10px] text-slate-400 font-mono">(${{ Number(item.unit_price).toFixed(2) }} c/u)</span>
                            </div>

                            <!-- Botón de eliminar registro por equivocación -->
                            <button
                                type="button"
                                @click="confirmDeleteRefill(item)"
                                class="h-9 w-9 rounded-xl flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition active:scale-95 cursor-pointer shrink-0"
                                title="Eliminar registro por equivocación"
                                aria-label="Eliminar registro"
                            >
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal de confirmación para eliminar registro de llenado -->
        <div
            v-if="refillToDelete"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            @click.self="cancelDeleteRefill"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 space-y-4 animate-scale-in">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-trash-can text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">¿Eliminar registro?</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Hora {{ refillToDelete.time }} · {{ refillToDelete.quantity }} botellón(es) (${{ Number(refillToDelete.total_amount).toFixed(2) }} MXN).
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    Se eliminará este registro por equivocación y se descontará del resumen de llenados e importes del día.
                </p>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="cancelDeleteRefill"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="deleteRefill"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition shadow-sm inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>{{ isDeleting ? 'Eliminando...' : 'Sí, eliminar' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

