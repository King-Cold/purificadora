<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    customersWithDebt: {
        type: Array,
        default: () => [],
    },
    selectedCustomer: {
        type: Object,
        default: null,
    },
    searchTerm: {
        type: String,
        default: '',
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.debt || null);

// Búsqueda
const searchQuery = ref(props.searchTerm || '');
const onSearchSubmit = () => {
    router.get(
        route('repartidor.debt.index'),
        { q: searchQuery.value },
        { preserveState: true, preserveScroll: true }
    );
};

// Cliente seleccionado
const activeCustomer = ref(props.selectedCustomer || null);

const selectCustomer = (cust) => {
    activeCustomer.value = cust;
    bottlesToSettle.value = Math.min(1, cust.pending_bottles || 1);
    cashTendered.value = calculateAmountFor(bottlesToSettle.value);
};

const clearCustomer = () => {
    activeCustomer.value = null;
    bottlesToSettle.value = 1;
    cashTendered.value = 0;
};

// Cálculo de liquidación por botellones completos a precios históricos
const bottlesToSettle = ref(1);
const cashTendered = ref(0);

const calculateAmountFor = (bottles) => {
    if (!activeCustomer.value || !activeCustomer.value.charges) return bottles * 15;
    let needed = bottles;
    let sum = 0;
    for (const c of activeCustomer.value.charges) {
        if (needed <= 0) break;
        const availableInCharge = c.unpaid_units || 0;
        const take = Math.min(needed, availableInCharge);
        sum += take * (c.unit_price || 15);
        needed -= take;
    }
    return sum;
};

const exactAmountToLiquidate = computed(() => {
    return calculateAmountFor(bottlesToSettle.value);
});

const changeReturned = computed(() => {
    const tendered = Number(cashTendered.value || 0);
    return Math.max(0, roundTwo(tendered - exactAmountToLiquidate.value));
});

const isInsufficientCash = computed(() => {
    return Number(cashTendered.value || 0) < exactAmountToLiquidate.value;
});

function roundTwo(num) {
    return Math.round((num + Number.EPSILON) * 100) / 100;
}

// Formulario de Liquidación
const settleForm = useForm({
    bottles_count: 1,
    cash_tendered: 0,
    notes: '',
});

const submitSettle = () => {
    if (!activeCustomer.value) return;
    if (isInsufficientCash.value) {
        alert(`El efectivo recibido ($${cashTendered.value}) es insuficiente para cubrir los $${exactAmountToLiquidate.value} requeridos.`);
        return;
    }

    settleForm.bottles_count = bottlesToSettle.value;
    settleForm.cash_tendered = cashTendered.value;

    settleForm.post(route('repartidor.customers.settle-debt', activeCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            clearCustomer();
        },
    });
};
</script>

<template>
    <Head title="Cobrar Pendientes · Repartidor Puritronic" />

    <AuthenticatedLayout>
        <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-5 pb-24 md:pb-16 pt-4">

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO Y REGRESO                                                    -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="route('repartidor.dashboard')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-50 active:scale-95 transition min-h-[44px]"
                >
                    <i class="fa-solid fa-arrow-left"></i> Panel Principal
                </Link>

                <div class="text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Cobranza Independiente</span>
                    <span class="text-xs font-black text-blue-900">Cobrar Pendientes de Pago</span>
                </div>
            </div>

            <!-- Alertas Flash -->
            <div v-if="flashSuccess" class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span>{{ flashSuccess }}</span>
            </div>
            <div v-if="flashError" class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span>{{ flashError }}</span>
            </div>

            <!-- Título de sección -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-2">
                <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-hand-holding-dollar text-emerald-600"></i> Cobro de Saldos Anteriores
                </h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Liquida botellones completos adeudados utilizando sus precios históricos, sin crear otro pedido ni forzar una entrega nueva.
                </p>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. BÚSQUEDA O SELECCIÓN DE CLIENTE                                        -->
            <!-- ========================================================================= -->
            <div v-if="!activeCustomer" class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-4">
                <form @submit.prevent="onSearchSubmit" class="space-y-2">
                    <label class="block text-xs font-black text-slate-700">
                        Buscar cliente con saldo pendiente:
                    </label>
                    <div class="flex gap-2">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Apodo, nombre o código CLI-XXXX..."
                            class="flex-1 text-sm py-2.5 px-3 rounded-xl border border-slate-200 bg-white"
                        />
                        <button
                            type="submit"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white transition min-h-[44px]"
                        >
                            Buscar
                        </button>
                    </div>
                </form>

                <!-- Listado de clientes con adeudo -->
                <div class="space-y-2 pt-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block">
                        Clientes con botellones pendientes ({{ customersWithDebt.length }} encontrados):
                    </span>

                    <div v-if="customersWithDebt.length === 0" class="p-6 text-center text-xs text-slate-400 border border-dashed rounded-xl space-y-1">
                        <i class="fa-solid fa-circle-check text-2xl text-emerald-400"></i>
                        <p class="font-bold text-slate-700">No hay clientes con saldos pendientes</p>
                        <p>Todos los clientes están al día con sus pagos.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-100 border border-slate-100 rounded-xl max-h-80 overflow-y-auto">
                        <div
                            v-for="c in customersWithDebt"
                            :key="c.id"
                            @click="selectCustomer(c)"
                            class="p-3.5 flex items-center justify-between gap-3 hover:bg-emerald-50/40 cursor-pointer transition text-xs"
                        >
                            <div class="space-y-0.5 min-w-0">
                                <span class="font-black text-slate-900 block text-sm truncate">
                                    {{ c.nickname ? `"${c.nickname}"` : (c.name || 'Cliente sin nombre') }}
                                </span>
                                <span class="font-mono text-[10px] text-slate-400">
                                    {{ c.code }} <template v-if="c.name">• {{ c.name }}</template>
                                </span>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-800 border border-rose-200 font-black text-xs block">
                                    {{ c.pending_bottles }} garrafón(es)
                                </span>
                                <span class="text-[11px] font-bold text-slate-500 mt-0.5 block">
                                    Saldo: ${{ c.pending_amount }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. LIQUIDACIÓN DEL CLIENTE SELECCIONADO                                    -->
            <!-- ========================================================================= -->
            <div v-else class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-5">
                <!-- Tarjeta del cliente -->
                <div class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="font-mono text-[10px] text-emerald-700 font-bold block">{{ activeCustomer.code }}</span>
                        <span class="text-base font-black text-emerald-950 block truncate">{{ activeCustomer.nickname }}</span>
                        <span v-if="activeCustomer.name" class="text-slate-600 text-[11px] block truncate">{{ activeCustomer.name }}</span>
                        <span class="text-[11px] font-extrabold text-rose-700 block mt-0.5">
                            Total adeudado: {{ activeCustomer.pending_bottles }} garrafón(es) (${{ activeCustomer.pending_amount }})
                        </span>
                    </div>
                    <button
                        @click="clearCustomer"
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 shrink-0 min-h-[44px]"
                    >
                        Cambiar
                    </button>
                </div>

                <!-- Detalle de cargos históricos -->
                <div class="space-y-2">
                    <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider block">
                        Desglose de cargos pendientes (orden de antigüedad):
                    </span>
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 divide-y divide-slate-200/60 text-xs">
                        <div
                            v-for="ch in activeCustomer.charges"
                            :key="ch.id"
                            class="py-2 first:pt-0 last:pb-0 flex items-center justify-between"
                        >
                            <div>
                                <span class="font-bold text-slate-800">{{ ch.order_folio }}</span>
                                <span class="text-[11px] text-slate-400 block">{{ ch.date }}</span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-slate-900">{{ ch.unpaid_units }} garrafón(es) a ${{ ch.unit_price }}</span>
                                <span class="text-[11px] text-slate-500 block">Saldo: ${{ ch.remaining_amount }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Cobro -->
                <form @submit.prevent="submitSettle" class="space-y-4 text-xs">
                    <!-- Selector de garrafones completos a liquidar -->
                    <div>
                        <label class="block font-black text-slate-700 mb-1">
                            Garrafones completos a liquidar:
                        </label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model.number="bottlesToSettle"
                                type="number"
                                min="1"
                                :max="activeCustomer.pending_bottles"
                                required
                                class="w-24 text-base font-black py-2 px-3 rounded-xl border border-slate-300 text-center"
                            />
                            <span class="text-slate-500 text-xs">
                                de {{ activeCustomer.pending_bottles }} pendiente(s)
                            </span>
                        </div>
                    </div>

                    <!-- Importe exacto calculado -->
                    <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200/70 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-blue-900">Importe exacto a liquidar:</span>
                            <span class="text-lg font-black text-blue-950">${{ exactAmountToLiquidate }}</span>
                        </div>
                        <p class="text-[11px] text-blue-700">
                            Calculado con los precios históricos de cada garrafón adeudado.
                        </p>
                    </div>

                    <!-- Efectivo recibido y cambio -->
                    <div class="space-y-3 pt-1">
                        <div>
                            <label class="block font-black text-slate-700 mb-1">
                                Efectivo recibido del cliente ($):
                            </label>
                            <input
                                v-model.number="cashTendered"
                                type="number"
                                min="0"
                                step="any"
                                required
                                class="w-full text-lg font-black py-2.5 px-3 rounded-xl border border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>

                        <!-- Cambio devuelto en vivo -->
                        <div
                            class="p-3.5 rounded-xl border text-xs flex items-center justify-between"
                            :class="isInsufficientCash 
                                ? 'bg-rose-50 border-rose-200 text-rose-900' 
                                : 'bg-emerald-50 border-emerald-200 text-emerald-900'"
                        >
                            <span class="font-bold flex items-center gap-1.5">
                                <i :class="isInsufficientCash ? 'fa-solid fa-circle-exclamation text-rose-600' : 'fa-solid fa-coins text-emerald-600'"></i>
                                {{ isInsufficientCash ? 'Efectivo insuficiente' : 'Cambio a devolver al cliente:' }}
                            </span>
                            <span class="text-base font-black">
                                ${{ isInsufficientCash ? 0 : changeReturned }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Notas / Referencia (opcional):</label>
                        <input
                            v-model="settleForm.notes"
                            type="text"
                            placeholder="Ej. Cobro de saldo en domicilio"
                            class="w-full text-xs py-2 px-3 rounded-xl border border-slate-300"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            @click="clearCustomer"
                            type="button"
                            class="px-4 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="settleForm.processing || isInsufficientCash"
                            class="px-6 py-2.5 rounded-xl font-black bg-emerald-600 hover:bg-emerald-700 text-white active:scale-95 transition disabled:opacity-50 shadow-2xs min-h-[44px] inline-flex items-center gap-1.5"
                        >
                            <i class="fa-solid fa-check"></i>
                            {{ settleForm.processing ? 'Procesando...' : 'Confirmar Cobro' }}
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Barra inferior móvil -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

