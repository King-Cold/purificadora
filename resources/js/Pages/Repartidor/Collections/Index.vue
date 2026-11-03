<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
const flashError = computed(() => page.props.errors?.collect || page.props.errors?.route || null);

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

// Modal de Recolección Directa
const showCollectModal = ref(false);
const orderToCollect = ref(null);
const collectForm = useForm({
    quantity: 1,
    notes: '',
});

const openCollectModal = (order) => {
    orderToCollect.value = order;
    collectForm.quantity = order.fixed_quantity_pending || 1;
    collectForm.notes = '';
    collectForm.clearErrors();
    showCollectModal.value = true;
};

const closeCollectModal = () => {
    showCollectModal.value = false;
    orderToCollect.value = null;
};

const submitCollection = () => {
    if (!orderToCollect.value) return;
    collectForm.post(route('repartidor.orders.collect-fixed', orderToCollect.value.id), {
        preserveScroll: true,
        onSuccess: () => closeCollectModal(),
    });
};
</script>

<template>
    <Head title="Por Recoger · Repartidor Puritronic" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-5 pb-16 pt-4">

            <!-- ========================================================================= -->
            <!-- 1. ENCABEZADO Y ACCESOS RÁPIDOS                                           -->
            <!-- ========================================================================= -->
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="route('repartidor.dashboard')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs hover:bg-slate-50 active:scale-95 transition min-h-[44px]"
                >
                    <i class="fa-solid fa-arrow-left"></i> Panel Principal
                </Link>

                <div class="text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Operación en Campo</span>
                    <span class="text-xs font-black text-blue-900">Por Recoger · Fijos Vacíos</span>
                </div>
            </div>

            <!-- Alertas flash -->
            <div v-if="flashSuccess" class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                <span>{{ flashSuccess }}</span>
            </div>
            <div v-if="flashError" class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base"></i>
                <span>{{ flashError }}</span>
            </div>

            <!-- Título y Resumen -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="space-y-0.5">
                        <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-hand-holding-hand text-amber-600"></i> Botellones Fijos por Recoger
                        </h2>
                        <p class="text-xs text-slate-500">
                            Envases vacíos de clientes que deben recogerse para trasladar a planta.
                        </p>
                    </div>
                    <span class="px-3 py-1 bg-amber-50 text-amber-900 border border-amber-200 text-xs font-extrabold rounded-xl self-start sm:self-auto">
                        {{ orders.length }} pedido(s) pendiente(s)
                    </span>
                </div>

                <!-- Estado de la ruta / Acción para iniciar si no está en curso -->
                <div
                    v-if="!activeRoute || !activeRoute.is_in_progress"
                    class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                >
                    <div class="space-y-0.5">
                        <span class="font-extrabold text-blue-950 block flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-info text-blue-700"></i>
                            {{ !activeRoute ? 'No tienes una ruta iniciada hoy' : (activeRoute.is_assigned ? 'Tu ruta asignada está pendiente de iniciar' : 'Ruta en pausa por incidencia') }}
                        </span>
                        <p class="text-blue-800">
                            Para registrar las recolecciones físicas y vincularlas a tu jornada, inicia el recorrido.
                        </p>
                    </div>
                    <Link
                        :href="route('repartidor.route.start-today')"
                        method="post"
                        as="button"
                        class="px-4 py-2.5 rounded-xl bg-blue-900 hover:bg-blue-800 text-white font-extrabold text-xs shadow-xs transition shrink-0 min-h-[44px] inline-flex items-center justify-center gap-1.5"
                    >
                        <i class="fa-solid fa-play"></i> Iniciar Jornada de Hoy
                    </Link>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. FILTRO POR ZONA                                                        -->
            <!-- ========================================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-3 shadow-2xs space-y-2">
                <div class="flex items-center justify-between px-1">
                    <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-compass text-blue-900"></i> Filtrar por Zona:
                    </span>
                    <button
                        v-if="selectedZone !== 'all'"
                        @click="selectedZone = 'all'"
                        type="button"
                        class="text-[11px] text-blue-900 font-bold hover:underline"
                    >
                        Ver todas
                    </button>
                </div>
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5">
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
                            class="text-[10px] px-1.5 py-0.2 rounded-full font-black"
                            :class="selectedZone === tab.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ zoneCounts[tab.key] || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. LISTADO DE PEDIDOS POR RECOGER                                         -->
            <!-- ========================================================================= -->
            <div v-if="filteredOrders.length === 0" class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center space-y-2">
                <i class="fa-solid fa-check-double text-3xl text-emerald-500"></i>
                <p class="text-sm font-extrabold text-slate-800">
                    No hay recolecciones pendientes de fijos en esta zona
                </p>
                <p class="text-xs text-slate-500">
                    Todos los botellones fijos programados han sido recolectados.
                </p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="order in filteredOrders"
                    :key="order.id"
                    class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs space-y-3 transition hover:border-slate-300"
                >
                    <!-- Cabecera del pedido -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <span class="text-base sm:text-lg font-black text-slate-900 block truncate">
                                {{ order.customer.nickname ? `"${order.customer.nickname}"` : (order.customer.name || 'Cliente sin nombre') }}
                            </span>
                            <span v-if="order.customer.nickname && order.customer.name" class="text-xs text-slate-500 block truncate">
                                Nombre: {{ order.customer.name }}
                            </span>
                            <span class="text-[11px] font-mono text-slate-400">
                                Código: {{ order.customer.code }} • Folio: {{ order.folio }}
                            </span>
                        </div>

                        <!-- Badge de estado de ruta -->
                        <div class="text-right shrink-0">
                            <span
                                v-if="order.is_assigned_to_current_route"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200 inline-flex items-center gap-1"
                            >
                                <i class="fa-solid fa-route"></i> Parada #{{ order.stop_number }}
                            </span>
                            <span
                                v-else-if="order.is_assigned_to_other_route"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200"
                            >
                                En hoja {{ order.assigned_route_folio }}
                            </span>
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200"
                            >
                                Por programar
                            </span>
                        </div>
                    </div>

                    <!-- Cantidad a recoger y zona -->
                    <div class="bg-amber-50/70 p-3 rounded-xl border border-amber-200/60 flex items-center justify-between text-xs">
                        <div>
                            <span class="text-slate-500 block text-[11px]">Por recoger:</span>
                            <span class="text-base font-black text-amber-950">
                                {{ order.fixed_quantity_pending }} garrafón(es) fijos vacíos
                            </span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-white border border-amber-200 font-extrabold text-amber-900 text-xs">
                            Zona {{ order.delivery_zone }}
                        </span>
                    </div>

                    <!-- Domicilio y teléfono -->
                    <div class="space-y-1 text-xs text-slate-600">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot text-slate-400 text-sm mt-0.5 shrink-0"></i>
                            <span class="font-medium text-slate-800">{{ order.address_summary }}</span>
                        </div>
                        <div v-if="order.customer.has_phone" class="pt-0.5">
                            <a
                                :href="`tel:${order.customer.phone}`"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold hover:bg-emerald-100 min-h-[44px]"
                            >
                                <i class="fa-solid fa-phone"></i> {{ order.customer.phone }}
                            </a>
                        </div>
                    </div>

                    <!-- Botón de Acción -->
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                        <div class="text-[11px] text-slate-400">
                            Estado: <span class="font-bold text-slate-700">{{ order.status_label }}</span>
                        </div>

                        <!-- Si está en la ruta activa en curso: Atender parada -->
                        <Link
                            v-if="order.is_assigned_to_current_route && activeRoute?.is_in_progress && order.stop_id"
                            :href="route('repartidor.stops.serve', order.stop_id)"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-2xs inline-flex items-center gap-1.5 min-h-[44px]"
                        >
                            <i class="fa-solid fa-hand-holding-hand"></i> Recoger (Atender Parada)
                        </Link>

                        <!-- Si la ruta está en curso pero no está asignado o es directo: Recoger directo -->
                        <button
                            v-else-if="activeRoute?.is_in_progress"
                            @click="openCollectModal(order)"
                            type="button"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-amber-600 hover:bg-amber-700 text-white transition active:scale-95 shadow-2xs inline-flex items-center gap-1.5 min-h-[44px]"
                        >
                            <i class="fa-solid fa-hand-holding-hand"></i> Recoger Ahora
                        </button>

                        <!-- Si la ruta no ha iniciado: invitar a iniciar -->
                        <Link
                            v-else
                            :href="route('repartidor.route.start-today')"
                            method="post"
                            as="button"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white transition active:scale-95 shadow-2xs inline-flex items-center gap-1.5 min-h-[44px]"
                        >
                            <i class="fa-solid fa-play"></i> Iniciar Ruta para Recoger
                        </Link>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR RECOLECCIÓN FÍSICA                                       -->
        <!-- ========================================================================= -->
        <div
            v-if="showCollectModal && orderToCollect"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-5 sm:p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-hand-holding-hand text-amber-600"></i> Confirmar Recolección
                    </h3>
                    <button @click="closeCollectModal" type="button" class="text-slate-400 hover:text-slate-600 p-2 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs space-y-1">
                    <p class="font-extrabold text-amber-950">Cliente: {{ orderToCollect.customer.nickname }}</p>
                    <p class="text-amber-800">
                        Los botellones fijos vacíos quedarán en custodia en tu camión en estado <strong>Llevando a planta</strong>. No se cobra rellenado hasta la entrega.
                    </p>
                </div>

                <form @submit.prevent="submitCollection" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Cantidad recolectada físicamente:</label>
                        <input
                            v-model.number="collectForm.quantity"
                            type="number"
                            min="1"
                            :max="orderToCollect.fixed_quantity_pending"
                            required
                            class="w-full text-base font-black py-2.5 px-3 rounded-xl border border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                        />
                        <p class="text-slate-400 text-[11px] mt-1">Máximo pendiente en pedido: {{ orderToCollect.fixed_quantity_pending }}</p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Notas / Observaciones (opcional):</label>
                        <textarea
                            v-model="collectForm.notes"
                            rows="2"
                            placeholder="Ej. Envases limpios entregados por el cliente"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            @click="closeCollectModal"
                            type="button"
                            class="px-4 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-100 min-h-[44px]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="collectForm.processing"
                            class="px-5 py-2.5 rounded-xl font-black bg-amber-600 hover:bg-amber-700 text-white active:scale-95 transition disabled:opacity-50 shadow-2xs min-h-[44px] inline-flex items-center gap-1.5"
                        >
                            <i class="fa-solid fa-check"></i> {{ collectForm.processing ? 'Guardando...' : 'Confirmar Recogida' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

