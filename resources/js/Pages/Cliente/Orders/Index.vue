<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: 'all' }),
    },
    activeOrder: {
        type: Object,
        default: null,
    },
    customer: {
        type: Object,
        required: true,
    },
});

const currentStatus = ref(props.filters.status || 'all');

const filterByStatus = (statusKey) => {
    currentStatus.value = statusKey;
    router.get(
        route('cliente.orders.index'),
        { status: statusKey },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-amber-50 text-amber-900 border-amber-200/80';
        case 'confirmed':
            return 'bg-blue-50 text-blue-900 border-blue-200/80';
        case 'cancelled':
            return 'bg-rose-50 text-rose-800 border-rose-200/80';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const getStatusIconClass = (status) => {
    switch (status) {
        case 'pending':
            return 'fa-regular fa-clock text-amber-600';
        case 'confirmed':
            return 'fa-solid fa-circle-check text-blue-700';
        case 'cancelled':
            return 'fa-solid fa-ban text-rose-600';
        default:
            return 'fa-solid fa-circle text-slate-400';
    }
};
</script>

<template>
    <Head title="Mis Pedidos - Puritronic" />

    <AuthenticatedLayout>
        <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
            <div class="mx-auto max-w-7xl 2xl:max-w-screen-2xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. ALERTA DESTACADA: PEDIDO ACTIVO EN CURSO (SI EXISTE) -->
                <div
                    v-if="activeOrder"
                    class="p-5 rounded-2xl bg-gradient-to-r from-blue-900 to-slate-900 text-white shadow-xs border border-blue-800/60 flex flex-col md:flex-row md:items-center justify-between gap-4"
                >
                    <div class="flex items-start sm:items-center gap-3.5">
                        <div class="h-11 w-11 rounded-xl bg-white/10 text-sky-400 flex items-center justify-center shrink-0 border border-white/10 text-lg">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div class="space-y-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-sky-200">
                                    Pedido Activo en Curso
                                </span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-mono font-bold bg-white text-blue-950">
                                    {{ activeOrder.folio }}
                                </span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-sky-400/20 text-sky-200 border border-sky-400/30">
                                    {{ activeOrder.status_label }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-300">
                                Solicitado el <strong>{{ activeOrder.created_at }}</strong> &bull; Total: <strong>{{ activeOrder.total_bottles }} botellón(es) de 20L</strong>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 self-end sm:self-auto shrink-0">
                        <Link
                            :href="route('cliente.orders.show', activeOrder.id)"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-blue-950 bg-white hover:bg-sky-50 transition shadow-2xs"
                        >
                            <i class="fa-regular fa-eye"></i>
                            <span>Ver Detalle del Pedido</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </Link>
                    </div>
                </div>

                <!-- 2. BARRA DE FILTROS POR ESTADO -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-xs border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 mr-1 hidden sm:inline">
                            Estado:
                        </span>

                        <button
                            type="button"
                            @click="filterByStatus('all')"
                            :class="[
                                'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer',
                                currentStatus === 'all'
                                    ? 'bg-blue-900 text-white shadow-2xs'
                                    : 'bg-slate-100 hover:bg-slate-200 text-slate-600'
                            ]"
                        >
                            Todos
                        </button>

                        <button
                            type="button"
                            @click="filterByStatus('pending')"
                            :class="[
                                'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                currentStatus === 'pending'
                                    ? 'bg-amber-600 text-white shadow-2xs'
                                    : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/60'
                            ]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="currentStatus === 'pending' ? 'bg-white' : 'bg-amber-500'"></span>
                            <span>Pendientes</span>
                        </button>

                        <button
                            type="button"
                            @click="filterByStatus('confirmed')"
                            :class="[
                                'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                currentStatus === 'confirmed'
                                    ? 'bg-blue-900 text-white shadow-2xs'
                                    : 'bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-200/60'
                            ]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="currentStatus === 'confirmed' ? 'bg-white' : 'bg-blue-600'"></span>
                            <span>Confirmados</span>
                        </button>

                        <button
                            type="button"
                            @click="filterByStatus('cancelled')"
                            :class="[
                                'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                currentStatus === 'cancelled'
                                    ? 'bg-rose-700 text-white shadow-2xs'
                                    : 'bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200/60'
                            ]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full" :class="currentStatus === 'cancelled' ? 'bg-white' : 'bg-rose-500'"></span>
                            <span>Cancelados</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-xs text-slate-500 hidden sm:block">
                            Total: <span class="font-bold text-slate-900">{{ orders.total }}</span> pedido(s)
                        </div>
                        <Link
                            :href="route('cliente.orders.create')"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer min-h-[40px] shrink-0"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Nuevo pedido</span>
                        </Link>
                    </div>
                </div>

                <!-- 3. LISTADO DE PEDIDOS (CARDS EN MÓVIL, TABLA EN ESCRITORIO) -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">

                    <!-- VISTA ESCRITORIO (TABLA) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="py-3.5 px-5">Folio</th>
                                    <th scope="col" class="py-3.5 px-4">Fecha</th>
                                    <th scope="col" class="py-3.5 px-4 text-center">Fijos (20L)</th>
                                    <th scope="col" class="py-3.5 px-4 text-center">Cambio (20L)</th>
                                    <th scope="col" class="py-3.5 px-4 text-center">Total (20L)</th>
                                    <th scope="col" class="py-3.5 px-4">Estado</th>
                                    <th scope="col" class="py-3.5 px-5 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="order in orders.data"
                                    :key="order.id"
                                    :class="[
                                        'hover:bg-slate-50/80 transition-colors',
                                        order.is_active ? 'bg-blue-50/30' : ''
                                    ]"
                                >
                                    <!-- Folio y badge si está activo -->
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-blue-950 text-sm">
                                                {{ order.folio }}
                                            </span>
                                            <span
                                                v-if="order.is_active"
                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300"
                                            >
                                                Activo
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fecha -->
                                    <td class="py-4 px-4 text-xs text-slate-600">
                                        {{ order.created_at }}
                                    </td>

                                    <!-- Fijos -->
                                    <td class="py-4 px-4 text-center font-mono font-bold text-blue-950">
                                        {{ order.fixed_quantity }}
                                    </td>

                                    <!-- Cambio -->
                                    <td class="py-4 px-4 text-center font-mono font-bold text-sky-800">
                                        {{ order.exchange_quantity }}
                                    </td>

                                    <!-- Total -->
                                    <td class="py-4 px-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                            {{ order.total_bottles }} envases
                                        </span>
                                    </td>

                                    <!-- Estado -->
                                    <td class="py-4 px-4">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border',
                                                getStatusBadgeClass(order.status)
                                            ]"
                                        >
                                            <i :class="getStatusIconClass(order.status)" class="text-[11px]"></i>
                                            <span>{{ order.status_label }}</span>
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="py-4 px-5 text-right">
                                        <Link
                                            :href="route('cliente.orders.show', order.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200/70 transition shadow-2xs"
                                        >
                                            <i class="fa-regular fa-eye text-xs"></i>
                                            <span>Ver Detalle</span>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- VISTA MÓVIL (TARJETAS TOUCH-FRIENDLY) -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <div
                            v-for="order in orders.data"
                            :key="order.id"
                            :class="[
                                'p-4 space-y-3 transition-colors',
                                order.is_active ? 'bg-blue-50/40' : ''
                            ]"
                        >
                            <!-- Cabecera de la tarjeta móvil -->
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-slate-900 text-base">
                                        {{ order.folio }}
                                    </span>
                                    <span
                                        v-if="order.is_active"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300"
                                    >
                                        Activo
                                    </span>
                                </div>

                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border',
                                        getStatusBadgeClass(order.status)
                                    ]"
                                >
                                    <i :class="getStatusIconClass(order.status)" class="text-[10px]"></i>
                                    <span>{{ order.status_label }}</span>
                                </span>
                            </div>

                            <p class="text-xs text-slate-500">
                                Solicitado: <strong>{{ order.created_at }}</strong>
                            </p>

                            <!-- Cuadrícula de botellones -->
                            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200/70 text-center">
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-400 uppercase block">Fijos 20L</span>
                                    <span class="font-mono font-bold text-blue-950 text-sm">{{ order.fixed_quantity }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] font-semibold text-slate-400 uppercase block">Cambio 20L</span>
                                    <span class="font-mono font-bold text-sky-800 text-sm">{{ order.exchange_quantity }}</span>
                                </div>
                                <div class="border-l border-slate-200">
                                    <span class="text-[10px] font-bold text-blue-900 uppercase block">Total 20L</span>
                                    <span class="font-mono font-bold text-slate-900 text-sm">{{ order.total_bottles }}</span>
                                </div>
                            </div>

                            <!-- Botón de acción móvil -->
                            <div class="pt-1 flex items-center justify-end">
                                <Link
                                    :href="route('cliente.orders.show', order.id)"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 transition"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                    <span>Consultar Detalle</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- 4. ESTADO VACÍO (SIN PEDIDOS REGISTRADOS O SEGÚN FILTRO) -->
                    <div v-if="orders.data.length === 0" class="py-12 px-4 text-center">
                        <div class="max-w-md mx-auto space-y-3">
                            <div class="h-16 w-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-2xl">
                                <i class="fa-regular fa-folder-open"></i>
                            </div>
                            <h4 class="text-base font-bold text-slate-900">
                                No se encontraron pedidos
                            </h4>
                            <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                                <template v-if="currentStatus !== 'all'">
                                    No tienes pedidos registrados en estado <strong class="text-slate-700 capitalize">{{ currentStatus }}</strong>.
                                </template>
                                <template v-else>
                                    Aún no has solicitado ningún pedido a domicilio en Puritronic.
                                </template>
                            </p>
                            <div class="pt-3 flex flex-wrap items-center justify-center gap-3">
                                <button
                                    v-if="currentStatus !== 'all'"
                                    type="button"
                                    @click="filterByStatus('all')"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition cursor-pointer"
                                >
                                    Ver todos los pedidos
                                </button>
                                <Link
                                    :href="route('cliente.orders.create')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs"
                                >
                                    <i class="fa-solid fa-plus-circle text-sky-400"></i>
                                    <span>Hacer mi primer pedido</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- 5. PAGINACIÓN CONSERVANDO EL FILTRO ACTIVO -->
                    <div
                        v-if="orders.total > 0 && orders.links && orders.links.length > 3"
                        class="bg-slate-50 px-4 py-3 sm:px-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-600"
                    >
                        <div>
                            Mostrando del <span class="font-bold">{{ orders.from || 0 }}</span> al <span class="font-bold">{{ orders.to || 0 }}</span> de <span class="font-bold">{{ orders.total }}</span> pedidos
                        </div>

                        <div class="flex flex-wrap items-center gap-1">
                            <template v-for="(link, key) in orders.links" :key="key">
                                <span
                                    v-if="link.url === null"
                                    class="px-3 py-1.5 rounded-lg text-slate-400 border border-slate-200 bg-white/60 cursor-not-allowed select-none"
                                    v-html="link.label"
                                />
                                <Link
                                    v-else
                                    :href="link.url"
                                    preserve-scroll
                                    preserve-state
                                    :class="[
                                        'px-3 py-1.5 rounded-lg font-medium border transition',
                                        link.active
                                            ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                                    ]"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
