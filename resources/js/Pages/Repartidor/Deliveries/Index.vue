<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    deliveries: {
        type: Array,
        default: () => [],
    },
    zoneCounts: {
        type: Object,
        default: () => ({ all: 0, north: 0, south: 0, east: 0, west: 0, none: 0 }),
    },
    truckExchangeAvailable: {
        type: Number,
        default: 0,
    },
    activeRoute: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.errors?.deliver || page.props.errors?.route || null);

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

const filteredDeliveries = computed(() => {
    if (selectedZone.value === 'all') {
        return props.deliveries;
    }
    return props.deliveries.filter(d => d.delivery_zone_key === selectedZone.value);
});

// Semáforo de adeudo
const getTrafficLightBadge = (tl) => {
    if (!tl) return { class: 'bg-slate-100 text-slate-700 border-slate-200', icon: 'fa-regular fa-circle text-slate-400', text: 'Sin datos' };
    const limit = tl.limit ?? 0;
    const pending = tl.pending_bottles ?? 0;

    if (limit === 0) {
        if (pending === 0) {
            return {
                class: 'bg-emerald-50 text-emerald-800 border-emerald-200 font-medium',
                icon: 'fa-solid fa-circle text-emerald-600',
                text: 'Fiado deshabilitado (0 pend.)',
            };
        }
        return {
            class: 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
            icon: 'fa-solid fa-circle text-rose-600',
            text: `Fiado deshabilitado (${pending} pend.)`,
        };
    }

    if (pending === 0 || tl.color === 'green') {
        return {
            class: 'bg-emerald-100 text-emerald-800 border-emerald-300 font-medium',
            icon: 'fa-solid fa-circle text-emerald-600',
            text: `Al día (0/${limit})`,
        };
    }

    if (pending < limit && tl.color === 'yellow') {
        return {
            class: 'bg-amber-100 text-amber-800 border-amber-300 font-semibold',
            icon: 'fa-solid fa-circle text-amber-500',
            text: `Con deuda (${pending}/${limit})`,
        };
    }

    return {
        class: 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
        icon: 'fa-solid fa-circle text-rose-600',
        text: `Límite alcanzado (${pending}/${limit})`,
    };
};
</script>

<template>
    <Head title="Por Entregar · Repartidor Puritronic" />

    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 space-y-5 pb-16 pt-4">

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
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Distribución en Calle</span>
                    <span class="text-xs font-black text-blue-900">Por Entregar · Garrafones Llenos</span>
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

            <!-- Título y Estado de existencias del camión -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-2xs space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="space-y-0.5">
                        <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-truck-ramp-box text-blue-900"></i> Pedidos por Entregar
                        </h2>
                        <p class="text-xs text-slate-500">
                            Entregas de botellones de cambio y devoluciones de fijos preparados.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <span class="px-3 py-1 bg-blue-50 text-blue-900 border border-blue-200 text-xs font-extrabold rounded-xl">
                            {{ deliveries.length }} pedido(s)
                        </span>
                    </div>
                </div>

                <!-- Disponibilidad en camión -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-boxes-stacked text-blue-900 text-base"></i>
                        <span class="font-bold text-slate-800">Existencias en camión para cambio:</span>
                        <span class="font-black text-blue-900 text-sm">{{ truckExchangeAvailable }} garrafones llenos</span>
                    </div>
                    <Link
                        :href="route('repartidor.truck-load.index')"
                        class="text-blue-900 font-extrabold hover:underline text-[11px] self-end sm:self-auto"
                    >
                        Gestionar Mi Carga →
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
            <!-- 3. LISTADO DE PEDIDOS POR ENTREGAR                                        -->
            <!-- ========================================================================= -->
            <div v-if="filteredDeliveries.length === 0" class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center space-y-2">
                <i class="fa-solid fa-box-open text-3xl text-slate-300"></i>
                <p class="text-sm font-extrabold text-slate-800">
                    No hay entregas pendientes en esta zona
                </p>
                <p class="text-xs text-slate-500">
                    Puedes consultar otras zonas o registrar un pedido al paso.
                </p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="delivery in filteredDeliveries"
                    :key="delivery.id"
                    class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-2xs space-y-3 transition hover:border-slate-300"
                >
                    <!-- Cabecera del pedido -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-0.5 min-w-0">
                            <span class="text-base sm:text-lg font-black text-slate-900 block truncate">
                                {{ delivery.customer.nickname ? `"${delivery.customer.nickname}"` : (delivery.customer.name || 'Cliente sin nombre') }}
                            </span>
                            <span v-if="delivery.customer.nickname && delivery.customer.name" class="text-xs text-slate-500 block truncate">
                                Nombre: {{ delivery.customer.name }}
                            </span>
                            <span class="text-[11px] font-mono text-slate-400">
                                Código: {{ delivery.customer.code }} • Folio: {{ delivery.folio }}
                            </span>
                        </div>

                        <!-- Badge de estado de ruta -->
                        <div class="text-right shrink-0">
                            <span
                                v-if="delivery.is_assigned_to_current_route"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200 inline-flex items-center gap-1"
                            >
                                <i class="fa-solid fa-route"></i> Parada #{{ delivery.stop_number }}
                            </span>
                            <span
                                v-else
                                class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200"
                            >
                                Por programar
                            </span>
                        </div>
                    </div>

                    <!-- Cantidad a entregar y Semáforo de deuda -->
                    <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="text-slate-500 block text-[11px]">Por entregar:</span>
                            <span class="text-base font-black text-blue-950">
                                {{ delivery.total_bottles_to_deliver }} garrafón(es)
                            </span>
                            <span class="text-[11px] text-slate-500 block mt-0.5" v-if="delivery.exchange_quantity > 0 && delivery.fixed_quantity > 0">
                                ({{ delivery.exchange_quantity }} cambio / {{ delivery.fixed_quantity }} fijos)
                            </span>
                        </div>

                        <!-- Semáforo -->
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-slate-400 font-bold uppercase">Semáforo:</span>
                            <span
                                class="px-2.5 py-1 rounded-lg text-xs border inline-flex items-center gap-1.5 font-bold"
                                :class="getTrafficLightBadge(delivery.traffic_light).class"
                            >
                                <i :class="getTrafficLightBadge(delivery.traffic_light).icon"></i>
                                <span>{{ getTrafficLightBadge(delivery.traffic_light).text }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Alerta de Bloqueo Explicativo (Sin ocultar el pedido) -->
                    <div
                        v-if="delivery.is_blocked"
                        class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-1"
                    >
                        <span class="font-extrabold flex items-center gap-1.5 text-amber-950">
                            <i class="fa-solid fa-triangle-exclamation text-amber-700"></i> Entrega en espera
                        </span>
                        <p class="text-amber-800">{{ delivery.block_reason }}</p>
                    </div>

                    <!-- Domicilio y teléfono -->
                    <div class="space-y-1 text-xs text-slate-600">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-location-dot text-slate-400 text-sm mt-0.5 shrink-0"></i>
                            <div>
                                <span class="font-medium text-slate-800 block">{{ delivery.address_summary }}</span>
                                <span class="text-[11px] text-slate-400">Zona {{ delivery.delivery_zone }}</span>
                            </div>
                        </div>
                        <div v-if="delivery.customer.has_phone" class="pt-0.5">
                            <a
                                :href="`tel:${delivery.customer.phone}`"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold hover:bg-emerald-100 min-h-[44px]"
                            >
                                <i class="fa-solid fa-phone"></i> {{ delivery.customer.phone }}
                            </a>
                        </div>
                    </div>

                    <!-- Botón de Acción -->
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                        <div class="text-[11px] text-slate-400">
                            Estado: <span class="font-bold text-slate-700">{{ delivery.status_label }}</span>
                        </div>

                        <!-- Si está en la ruta activa: Atender parada -->
                        <Link
                            v-if="delivery.is_assigned_to_current_route && activeRoute?.is_in_progress && delivery.stop_id"
                            :href="route('repartidor.stops.serve', delivery.stop_id)"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-2xs inline-flex items-center gap-1.5 min-h-[44px]"
                        >
                            <i class="fa-solid fa-bottle-water"></i> Entregar (Atender Parada)
                        </Link>

                        <!-- Si la ruta no ha iniciado: invitar a iniciar -->
                        <Link
                            v-else-if="!activeRoute?.is_in_progress"
                            :href="route('repartidor.route.start-today')"
                            method="post"
                            as="button"
                            class="px-4 py-2.5 rounded-xl text-xs font-black bg-blue-900 hover:bg-blue-800 text-white transition active:scale-95 shadow-2xs inline-flex items-center gap-1.5 min-h-[44px]"
                        >
                            <i class="fa-solid fa-play"></i> Iniciar Ruta para Entregar
                        </Link>

                        <!-- Si está bloqueado pero en ruta -->
                        <span v-else class="text-xs text-amber-700 font-bold">
                            Pendiente de carga o planta
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

