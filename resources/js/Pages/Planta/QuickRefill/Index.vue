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
    <Head title="Llenado rápido" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-7 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            <!-- ===================================================================== -->
            <!-- 1. FILA DE ESTADÍSTICAS COMPACTAS (ARRIBA EN DESKTOP, DEBAJO EN MÓVIL)-->
            <!-- ===================================================================== -->
            <div class="order-2 lg:order-1 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Tarjeta 1: Total mostrador hoy (Verde salvia con texto verde oscuro) -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#34785A] shrink-0"></span>
                            <span class="text-xs font-medium text-[#747181] truncate">Total mostrador hoy</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-1.5">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                ${{ Number(todaySummary.total_amount).toFixed(2) }}
                            </span>
                            <span class="text-xs font-semibold text-[#34785A]">MXN</span>
                        </div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-[#E8F2EC] text-[#34785A] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-money-bill-wave text-base"></i>
                    </div>
                </div>

                <!-- Tarjeta 2: Llenados hoy (Azul niebla con detalles azul corporativo) -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#0071BA] shrink-0"></span>
                            <span class="text-xs font-medium text-[#747181] truncate">Llenados hoy</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                {{ todaySummary.bottles_refilled }}
                            </span>
                            <span class="text-xs font-medium text-[#A6A3B2]">garrafones {{ capacity }}L</span>
                        </div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-[#E9F3F8] text-[#0071BA] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-bottle-water text-base"></i>
                    </div>
                </div>

                <!-- Tarjeta 3: Atenciones registradas (Lavanda suave con detalles morados) -->
                <div class="bg-white rounded-2xl border border-[#E9E7F0] px-5 py-4 shadow-[0_2px_12px_-6px_rgba(30,21,69,0.06)] flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#1E1545] shrink-0"></span>
                            <span class="text-xs font-medium text-[#747181] truncate">Atenciones registradas</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#292638] tracking-[-0.02em] tabular-nums">
                                {{ todaySummary.services_count }}
                            </span>
                            <span class="text-xs font-medium text-[#A6A3B2]">servicios hoy</span>
                        </div>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-[#DAD6E8]/70 text-[#1E1545] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-users text-base"></i>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- 2. ÁREA DE TRABAJO PRINCIPAL: FORMULARIO (5 COLS) + HISTORIAL (7 COLS)-->
            <!-- ===================================================================== -->
            <div class="order-1 lg:order-2 grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                <!-- COLUMNA IZQUIERDA: FORMULARIO DE LLENADO DE MOSTRADOR -->
                <div class="lg:col-span-5 bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] overflow-hidden">
                    <!-- Encabezado limpio del formulario -->
                    <div class="px-6 py-5 border-b border-[#E9E7F0] flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="h-10 w-10 rounded-xl bg-[#DAD6E8]/75 text-[#1E1545] flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-bolt text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-base sm:text-lg font-bold text-[#292638] tracking-[-0.015em] leading-snug">
                                    Llenado de mostrador
                                </h1>
                                <p class="text-xs text-[#747181] mt-0.5 truncate">
                                    Clientes particulares · Garrafones de {{ capacity }}L
                                </p>
                            </div>
                        </div>

                        <!-- Etiqueta elegante de precio vigente -->
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-[#E9F3F8] border border-[#DCECF7] shrink-0">
                            <span class="text-[11px] font-medium text-[#747181]">Precio:</span>
                            <span class="text-xs font-bold text-[#0071BA] tabular-nums">
                                ${{ Number(unitPrice).toFixed(2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Cuerpo unificado del formulario -->
                    <form @submit.prevent="submitRefill" class="p-6 space-y-6">
                        <!-- Zona de selección de cantidad -->
                        <div class="rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] p-5 space-y-5">
                            <div class="flex items-center justify-between">
                                <label for="bottle-quantity" class="text-xs font-semibold text-[#747181]">
                                    Cantidad de botellones
                                </label>
                                <span class="text-xs font-medium text-[#0071BA]">
                                    {{ capacity }} litros c/u
                                </span>
                            </div>

                            <!-- Control numérico táctil -->
                            <div class="flex items-center justify-center gap-3.5">
                                <button
                                    type="button"
                                    @click="decrement(1)"
                                    :disabled="form.quantity <= 1 || form.processing"
                                    class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl bg-white hover:bg-[#E9F3F8] text-[#292638] hover:text-[#0071BA] border border-[#DAD6E8] hover:border-[#0071BA]/50 active:scale-95 transition-all duration-150 flex items-center justify-center text-lg disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:border-[#E9E7F0] disabled:pointer-events-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#0071BA]/25 shrink-0"
                                    aria-label="Restar un botellón"
                                >
                                    <i class="fa-solid fa-minus"></i>
                                </button>

                                <div class="flex-1 max-w-[156px] rounded-xl bg-white border border-[#DAD6E8] focus-within:border-[#1E1545] focus-within:ring-2 focus-within:ring-[#DAD6E8] px-3 py-2.5 transition-all">
                                    <input
                                        id="bottle-quantity"
                                        v-model.number="form.quantity"
                                        type="number"
                                        min="1"
                                        max="999"
                                        step="1"
                                        required
                                        class="w-full text-center text-3xl sm:text-4xl font-bold text-[#292638] bg-transparent border-0 p-0 focus:ring-0 focus:outline-none tabular-nums leading-none"
                                    />
                                    <span class="block text-center text-[11px] font-medium text-[#A6A3B2] mt-1">
                                        botellones
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    @click="increment(1)"
                                    :disabled="form.processing"
                                    class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl bg-[#DCECF7] hover:bg-[#0071BA] text-[#0071BA] hover:text-white border border-[#0071BA]/25 hover:border-[#0071BA] active:scale-95 transition-all duration-150 flex items-center justify-center text-lg cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#0071BA]/30 disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:border-[#E9E7F0] shrink-0"
                                    aria-label="Sumar un botellón"
                                >
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>

                            <!-- Cantidades rápidas con diferenciación clara -->
                            <div class="pt-1">
                                <div class="grid grid-cols-6 gap-1.5">
                                    <button
                                        v-for="n in [1, 2, 3, 4, 5, 10]"
                                        :key="n"
                                        type="button"
                                        @click="setQuantity(n)"
                                        :class="[
                                            'min-h-[40px] rounded-xl text-xs font-semibold transition-all duration-150 cursor-pointer tabular-nums border focus:outline-none focus:ring-2 focus:ring-[#0071BA]/25',
                                            form.quantity === n
                                                ? 'bg-[#E82147] text-white border-[#E82147] shadow-xs'
                                                : 'bg-white text-[#747181] border-[#E9E7F0] hover:border-[#0071BA]/45 hover:text-[#0071BA] hover:bg-[#E9F3F8]/60'
                                        ]"
                                    >
                                        {{ n }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Importe Calculado con jerarquía tipográfica -->
                        <div class="pt-4 border-t border-[#E9E7F0] flex items-end justify-between gap-4">
                            <div>
                                <span class="block text-xs font-medium text-[#747181]">
                                    Importe total
                                </span>
                                <span class="block text-xs font-medium text-[#A6A3B2] tabular-nums mt-0.5">
                                    {{ form.quantity || 0 }} × ${{ Number(unitPrice).toFixed(2) }} MXN
                                </span>
                            </div>

                            <div class="text-right">
                                <span class="text-3xl font-bold tracking-[-0.025em] text-[#34785A] tabular-nums leading-none">
                                    ${{ currentTotal }}
                                </span>
                                <span class="text-xs font-semibold text-[#747181] ml-1">MXN</span>
                            </div>
                        </div>

                        <!-- Botón Principal en Morado Profundo -->
                        <button
                            type="submit"
                            :disabled="form.processing || isSubmitting"
                            class="w-full min-h-[50px] px-5 rounded-xl bg-[#1E1545] hover:bg-[#2B205E] active:bg-[#150E33] text-white font-semibold text-sm sm:text-[15px] flex items-center justify-center gap-2.5 shadow-sm active:scale-[0.99] transition-all duration-150 cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2] disabled:shadow-none disabled:cursor-not-allowed focus:outline-none focus:ring-4 focus:ring-[#DAD6E8]"
                        >
                            <i v-if="form.processing || isSubmitting" class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                            <i v-else class="fa-solid fa-check text-sm text-white/90"></i>
                            <span class="tabular-nums">
                                Registrar {{ form.quantity }} llenado{{ form.quantity > 1 ? 's' : '' }} (${{ currentTotal }})
                            </span>
                        </button>
                    </form>
                </div>

                <!-- COLUMNA DERECHA: HISTORIAL DE ATENCIONES REGISTRADAS HOY -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-[#E9E7F0] shadow-[0_4px_20px_-8px_rgba(30,21,69,0.07)] overflow-hidden">
                    <!-- Encabezado de la tabla -->
                    <div class="px-6 py-5 border-b border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-[#292638] tracking-[-0.015em]">
                                Atenciones registradas hoy
                            </h2>
                            <p class="text-xs text-[#747181] mt-0.5">
                                Historial cronológico del turno en mostrador
                            </p>
                        </div>

                        <span
                            :class="[
                                'inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium border tabular-nums',
                                recentRefills.length > 0
                                    ? 'bg-[#E9F3F8] text-[#0071BA] border-[#DCECF7]'
                                    : 'bg-[#F4F3F8] text-[#747181] border-[#E9E7F0]'
                            ]"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="recentRefills.length > 0 ? 'bg-[#0071BA]' : 'bg-[#A6A3B2]'"
                            ></span>
                            {{ recentRefills.length }} registro{{ recentRefills.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <!-- Estado Vacío Minimalista y Diseñado -->
                    <div v-if="recentRefills.length === 0" class="py-14 px-6 text-center">
                        <div class="relative inline-flex items-center justify-center mb-4">
                            <div class="h-14 w-14 rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] flex items-center justify-center text-[#1E1545]">
                                <i class="fa-solid fa-droplet text-xl"></i>
                            </div>
                            <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-[#DCECF7] border-2 border-white"></span>
                        </div>

                        <h3 class="text-sm font-semibold text-[#292638]">
                            No se han registrado llenados hoy
                        </h3>
                        <p class="text-xs text-[#747181] max-w-xs mx-auto mt-1.5 leading-relaxed">
                            Registra la primera atención en el panel de mostrador para visualizar aquí la hora, operador e importe.
                        </p>

                        <div class="mt-5 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#F4F3F8] border border-[#E9E7F0] text-xs font-medium text-[#747181]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#34785A]"></span>
                            <span>Mostrador disponible · ${{ Number(unitPrice).toFixed(2) }} MXN c/u</span>
                        </div>
                    </div>

                    <!-- Tabla Moderna y Minimalista -->
                    <template v-else>
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-[#F4F3F8] border-b border-[#E9E7F0] text-xs font-medium text-[#747181]">
                                        <th class="py-3 px-6">Hora</th>
                                        <th class="py-3 px-4">Cantidad</th>
                                        <th class="py-3 px-4">Operador</th>
                                        <th class="py-3 px-4 text-right">Unitario</th>
                                        <th class="py-3 px-4 text-right">Importe</th>
                                        <th class="py-3 px-5 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E9E7F0]/75 text-sm">
                                    <tr
                                        v-for="item in recentRefills"
                                        :key="item.id"
                                        class="hover:bg-[#F4F3F8]/60 transition-colors"
                                    >
                                        <td class="py-3.5 px-6 whitespace-nowrap text-xs font-medium text-[#747181] tabular-nums">
                                            {{ item.time }}
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-lg bg-[#E9F3F8] text-[#0071BA] text-xs font-semibold tabular-nums">
                                                    {{ item.quantity }}
                                                </span>
                                                <span class="text-xs font-medium text-[#292638]">
                                                    botellón{{ item.quantity > 1 ? 'es' : '' }} ({{ capacity }}L)
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 whitespace-nowrap text-xs text-[#747181]">
                                            {{ item.operator_name }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap text-xs text-[#A6A3B2] tabular-nums">
                                            ${{ Number(item.unit_price).toFixed(2) }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-[#E8F2EC] text-[#34785A] text-xs font-semibold tabular-nums">
                                                ${{ Number(item.total_amount).toFixed(2) }} MXN
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                            <button
                                                type="button"
                                                @click="confirmDeleteRefill(item)"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg text-[#A6A3B2] hover:text-[#E82147] hover:bg-[#FCECEF] transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-[#E82147]/20"
                                                title="Eliminar registro por equivocación"
                                                aria-label="Eliminar registro"
                                            >
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Vista Móvil (< sm) -->
                        <div class="sm:hidden divide-y divide-[#E9E7F0]/75">
                            <div
                                v-for="item in recentRefills"
                                :key="item.id"
                                class="px-5 py-3.5 flex items-center justify-between gap-3 hover:bg-[#F4F3F8]/60 transition-colors"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="inline-flex items-center justify-center h-9 w-9 rounded-xl bg-[#E9F3F8] text-[#0071BA] text-xs font-semibold tabular-nums shrink-0">
                                        {{ item.quantity }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-[#292638] truncate">
                                            {{ item.quantity }} botellón{{ item.quantity > 1 ? 'es' : '' }} ({{ capacity }}L)
                                        </p>
                                        <p class="text-[11px] text-[#747181] truncate mt-0.5 tabular-nums">
                                            {{ item.time }} · {{ item.operator_name }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="px-2 py-0.5 rounded-lg bg-[#E8F2EC] text-[#34785A] text-xs font-semibold tabular-nums">
                                        ${{ Number(item.total_amount).toFixed(2) }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="confirmDeleteRefill(item)"
                                        class="h-8 w-8 rounded-lg flex items-center justify-center text-[#A6A3B2] hover:text-[#E82147] hover:bg-[#FCECEF] transition-colors cursor-pointer"
                                        title="Eliminar registro por equivocación"
                                        aria-label="Eliminar registro"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>

        </div>

        <!-- Modal de confirmación para eliminar registro de llenado -->
        <div
            v-if="refillToDelete"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#1E1545]/35 backdrop-blur-[2px]"
            @click.self="cancelDeleteRefill"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-[0_20px_50px_-12px_rgba(30,21,69,0.22)] border border-[#E9E7F0] space-y-4">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-[#FCECEF] text-[#E82147] flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-trash-can text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[#292638]">¿Eliminar registro?</h3>
                        <p class="text-xs text-[#747181] mt-1 tabular-nums">
                            Hora {{ refillToDelete.time }} · {{ refillToDelete.quantity }} botellón(es) (${{ Number(refillToDelete.total_amount).toFixed(2) }} MXN).
                        </p>
                    </div>
                </div>

                <p class="text-xs text-[#747181] bg-[#F4F3F8] p-3.5 rounded-xl border border-[#E9E7F0] leading-relaxed">
                    Se eliminará este registro por equivocación y se descontará del resumen de llenados e importes del día.
                </p>

                <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-[#E9E7F0]">
                    <button
                        type="button"
                        @click="cancelDeleteRefill"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-[#747181] bg-[#F4F3F8] hover:bg-[#E9E7F0] hover:text-[#292638] border border-[#E9E7F0] transition-colors cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="deleteRefill"
                        :disabled="isDeleting"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#E82147] hover:bg-[#CF1B3E] transition-colors inline-flex items-center gap-1.5 cursor-pointer disabled:bg-[#E9E7F0] disabled:text-[#A6A3B2]"
                    >
                        <i class="fa-solid fa-trash-can text-xs"></i>
                        <span>{{ isDeleting ? 'Eliminando...' : 'Sí, eliminar' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
