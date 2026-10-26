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
        default: () => ({ search: '', status: 'all', date_from: '', date_to: '' }),
    },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

let searchDebounceTimer = null;

const applyFilters = () => {
    router.get(
        route('admin.orders.index'),
        {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const onSearchInput = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 350);
};

const resetFilters = () => {
    search.value = '';
    status.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
};

const hasActiveFilters = () => {
    return Boolean(search.value || (status.value && status.value !== 'all') || dateFrom.value || dateTo.value);
};

const getStatusBadgeClass = (statusKey) => {
    switch (statusKey) {
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

const getStatusIconClass = (statusKey) => {
    switch (statusKey) {
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
    <Head title="Gestión de Pedidos - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-sm shadow-xs shrink-0">
                        <i class="fa-solid fa-file-invoice text-sky-400 text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                            Gestión de Pedidos
                        </h2>
                        <p class="text-xs text-slate-500">
                            Consulta global, confirmación y registro administrativo de pedidos
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 self-start sm:self-auto">
                    <Link
                        :href="route('admin.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-plus-circle text-sky-400"></i>
                        <span>Registrar Pedido</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-7xl 2xl:max-w-screen-2xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. BARRA DE BÚSQUEDA Y FILTROS -->
                <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">

                        <!-- Buscador -->
                        <div class="lg:col-span-4">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Búsqueda
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </span>
                                <input
                                    v-model="search"
                                    @input="onSearchInput"
                                    type="text"
                                    placeholder="Buscar folio, cliente, apodo o código..."
                                    class="w-full pl-9 pr-3 py-2 rounded-xl border-slate-300 text-xs sm:text-sm focus:border-blue-900 focus:ring-blue-900 placeholder:text-slate-400"
                                />
                            </div>
                        </div>

                        <!-- Filtro Estado -->
                        <div class="lg:col-span-3">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Estado
                            </label>
                            <select
                                v-model="status"
                                @change="applyFilters"
                                class="w-full py-2 px-3 rounded-xl border-slate-300 text-xs sm:text-sm focus:border-blue-900 focus:ring-blue-900 font-medium text-slate-700"
                            >
                                <option value="all">Todos los estados</option>
                                <option value="pending">Pendientes</option>
                                <option value="confirmed">Confirmados</option>
                                <option value="cancelled">Cancelados</option>
                            </select>
                        </div>

                        <!-- Rango Fecha Desde -->
                        <div class="lg:col-span-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Desde
                            </label>
                            <input
                                v-model="dateFrom"
                                @change="applyFilters"
                                type="date"
                                class="w-full py-2 px-3 rounded-xl border-slate-300 text-xs focus:border-blue-900 focus:ring-blue-900 text-slate-700"
                            />
                        </div>

                        <!-- Rango Fecha Hasta -->
                        <div class="lg:col-span-2">
                            <label class="text-xs font-bold text-slate-600 uppercase tracking-wider block mb-1">
                                Hasta
                            </label>
                            <input
                                v-model="dateTo"
                                @change="applyFilters"
                                type="date"
                                class="w-full py-2 px-3 rounded-xl border-slate-300 text-xs focus:border-blue-900 focus:ring-blue-900 text-slate-700"
                            />
                        </div>

                        <!-- Botón Limpiar -->
                        <div class="lg:col-span-1 flex justify-end">
                            <button
                                v-if="hasActiveFilters()"
                                @click="resetFilters"
                                type="button"
                                class="w-full py-2 px-3 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer"
                                title="Limpiar filtros"
                            >
                                <i class="fa-solid fa-xmark mr-1"></i>
                                Limpiar
                            </button>
                        </div>

                    </div>

                    <!-- Resumen de resultados filtrados -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <div>
                            <span>Total de pedidos encontrados: <strong class="text-slate-900">{{ orders.total }}</strong></span>
                            <span v-if="hasActiveFilters()" class="text-blue-900 ml-1.5 font-semibold">(filtros aplicados)</span>
                        </div>
                    </div>
                </div>

                <!-- 2. TABLA GLOBAL DE PEDIDOS -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">

                    <!-- VISTA ESCRITORIO -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="py-3.5 px-5">Folio</th>
                                    <th scope="col" class="py-3.5 px-4">Cliente</th>
                                    <th scope="col" class="py-3.5 px-4">Fecha</th>
                                    <th scope="col" class="py-3.5 px-3 text-center">Fijos (20L)</th>
                                    <th scope="col" class="py-3.5 px-3 text-center">Cambio (20L)</th>
                                    <th scope="col" class="py-3.5 px-3 text-center">Total</th>
                                    <th scope="col" class="py-3.5 px-4">Estado</th>
                                    <th scope="col" class="py-3.5 px-5 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="order in orders.data"
                                    :key="order.id"
                                    class="hover:bg-slate-50/80 transition-colors"
                                >
                                    <!-- Folio -->
                                    <td class="py-4 px-5">
                                        <span class="font-mono font-bold text-blue-950 text-sm">
                                            {{ order.folio }}
                                        </span>
                                    </td>

                                    <!-- Cliente, apodo, código y tipo de cuenta -->
                                    <td class="py-4 px-4">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900">
                                                    {{ order.customer?.name || 'Cliente sin nombre' }}
                                                </span>
                                                <span
                                                    v-if="order.customer?.nickname"
                                                    class="text-xs text-slate-500 font-medium"
                                                >
                                                    "{{ order.customer.nickname }}"
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 text-xs">
                                                <span class="font-mono text-slate-500">
                                                    {{ order.customer?.code }}
                                                </span>
                                                <span
                                                    :class="[
                                                        'px-2 py-0.5 rounded-md text-[10px] font-semibold border',
                                                        order.customer?.has_account
                                                            ? 'bg-blue-50 text-blue-900 border-blue-200'
                                                            : 'bg-slate-100 text-slate-600 border-slate-200'
                                                    ]"
                                                >
                                                    {{ order.customer?.has_account ? 'Con cuenta' : 'Sin cuenta' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Fecha -->
                                    <td class="py-4 px-4 text-xs text-slate-600 whitespace-nowrap">
                                        {{ order.created_at }}
                                    </td>

                                    <!-- Fijos -->
                                    <td class="py-4 px-3 text-center font-mono font-bold text-blue-950">
                                        {{ order.fixed_quantity }}
                                    </td>

                                    <!-- Cambio -->
                                    <td class="py-4 px-3 text-center font-mono font-bold text-sky-800">
                                        {{ order.exchange_quantity }}
                                    </td>

                                    <!-- Total -->
                                    <td class="py-4 px-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-mono font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                            {{ order.total_bottles }} env.
                                        </span>
                                    </td>

                                    <!-- Estado -->
                                    <td class="py-4 px-4 whitespace-nowrap">
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
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <Link
                                            :href="route('admin.orders.show', order.id)"
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

                    <!-- VISTA MÓVIL (TARJETAS) -->
                    <div class="lg:hidden divide-y divide-slate-100">
                        <div
                            v-for="order in orders.data"
                            :key="order.id"
                            class="p-4 space-y-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono font-bold text-blue-950 text-base">
                                    {{ order.folio }}
                                </span>
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

                            <div class="space-y-0.5">
                                <p class="text-sm font-bold text-slate-900">
                                    {{ order.customer?.name }}
                                    <span v-if="order.customer?.nickname" class="text-xs font-normal text-slate-500">
                                        "{{ order.customer.nickname }}"
                                    </span>
                                </p>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <span class="font-mono">{{ order.customer?.code }}</span>
                                    <span>&bull;</span>
                                    <span>{{ order.customer?.has_account ? 'Con cuenta' : 'Sin cuenta' }}</span>
                                    <span>&bull;</span>
                                    <span>{{ order.created_at }}</span>
                                </div>
                            </div>

                            <!-- Botellones Desglose Móvil -->
                            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200/70 text-center text-xs">
                                <div>
                                    <span class="text-[10px] text-slate-400 block uppercase">Fijos 20L</span>
                                    <span class="font-mono font-bold text-blue-950">{{ order.fixed_quantity }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-400 block uppercase">Cambio 20L</span>
                                    <span class="font-mono font-bold text-sky-800">{{ order.exchange_quantity }}</span>
                                </div>
                                <div class="border-l border-slate-200">
                                    <span class="text-[10px] text-blue-900 font-bold block uppercase">Total</span>
                                    <span class="font-mono font-bold text-slate-900">{{ order.total_bottles }}</span>
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <Link
                                    :href="route('admin.orders.show', order.id)"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition"
                                >
                                    <i class="fa-regular fa-eye"></i>
                                    <span>Ver Detalle del Pedido</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- ESTADO VACÍO -->
                    <div v-if="orders.data.length === 0" class="py-12 px-4 text-center">
                        <div class="max-w-md mx-auto space-y-3">
                            <div class="h-16 w-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-2xl">
                                <i class="fa-solid fa-boxes-packing"></i>
                            </div>
                            <h4 class="text-base font-bold text-slate-900">
                                No se encontraron pedidos
                            </h4>
                            <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                                No existen pedidos que coincidan con los filtros o parámetros de búsqueda aplicados.
                            </p>
                            <div class="pt-2 flex justify-center gap-3">
                                <button
                                    v-if="hasActiveFilters()"
                                    type="button"
                                    @click="resetFilters"
                                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition cursor-pointer"
                                >
                                    Limpiar filtros
                                </button>
                                <Link
                                    :href="route('admin.orders.create')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs"
                                >
                                    <i class="fa-solid fa-plus-circle text-sky-400"></i>
                                    <span>Registrar Pedido</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- PAGINACIÓN -->
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
