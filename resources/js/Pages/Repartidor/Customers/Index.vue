<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RepartidorBottomNav from '@/Components/RepartidorBottomNav.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});

const searchTerm = ref(props.filters.search || '');

const handleSearch = () => {
    router.get(
        route('repartidor.customers.index'),
        { search: searchTerm.value },
        { preserveState: true, replace: true }
    );
};

const clearSearch = () => {
    searchTerm.value = '';
    handleSearch();
};
</script>

<template>
    <Head title="Directorio de Clientes" />

    <AuthenticatedLayout wide>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2.5">
                        <i class="fa-solid fa-users text-blue-900"></i>
                        <span>Directorio de Clientes</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Consulta clientes registrados, su semáforo de crédito y registra nuevos servicios.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 shadow-sm active:scale-95 transition min-h-[44px]"
                    >
                        <i class="fa-solid fa-user-plus text-sky-400"></i>
                        <span>Agregar cliente</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 pb-24 md:pb-8">

            <!-- Buscador -->
            <div class="bg-white rounded-2xl border border-slate-200/90 p-3 sm:p-4 shadow-2xs">
                <form @submit.prevent="handleSearch" class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-sm"></i>
                        </div>
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Buscar por apodo, nombre, código (CLI-XXXX) o teléfono..."
                            class="w-full pl-10 pr-10 py-2.5 rounded-xl border-slate-200 text-xs sm:text-sm focus:border-blue-900 focus:ring-blue-900 transition"
                        />
                        <button
                            v-if="searchTerm"
                            @click="clearSearch"
                            type="button"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
                        >
                            <i class="fa-solid fa-circle-xmark text-sm"></i>
                        </button>
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    >
                        Buscar
                    </button>
                </form>
            </div>

            <!-- Listado de Clientes -->
            <div v-if="customers.data.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center shadow-2xs space-y-3">
                <div class="h-14 w-14 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <h3 class="text-base font-bold text-slate-800">No se encontraron clientes</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto">
                    {{ searchTerm ? 'No hay resultados que coincidan con tu búsqueda.' : 'Aún no hay clientes registrados.' }}
                </p>
                <div class="pt-2">
                    <Link
                        :href="route('repartidor.customers.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Agregar cliente ahora</span>
                    </Link>
                </div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="customer in customers.data"
                    :key="customer.id"
                    class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-2xs hover:shadow-sm hover:border-slate-300 transition flex flex-col justify-between space-y-3.5"
                >
                    <!-- Encabezado Cliente -->
                    <div class="space-y-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-[10px] font-black font-mono tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ customer.code }}
                                </span>
                                <h3 class="text-base font-extrabold text-slate-900 mt-1">
                                    {{ customer.nickname }}
                                </h3>
                                <p v-if="customer.name" class="text-xs text-slate-500">
                                    {{ customer.name }}
                                </p>
                            </div>

                            <!-- Semáforo de Crédito -->
                            <div class="shrink-0">
                                <span
                                    v-if="customer.traffic_light?.color === 'green'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                >
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    <span>Al corriente</span>
                                </span>
                                <span
                                    v-else-if="customer.traffic_light?.color === 'yellow'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200"
                                >
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span>Con deuda (${{ customer.pending_debt_amount }} · {{ customer.pending_debt_bottles }}/5)</span>
                                </span>
                                <span
                                    v-else-if="customer.traffic_light?.color === 'red'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200"
                                >
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                    <span>Límite (${{ customer.pending_debt_amount }})</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600"
                                >
                                    {{ customer.traffic_light?.label || 'Fiado deshabilitado' }}
                                </span>
                            </div>
                        </div>

                        <!-- Dirección y Zona -->
                        <div class="text-xs text-slate-600 flex items-start gap-1.5 pt-1">
                            <i class="fa-solid fa-location-dot text-slate-400 mt-0.5 shrink-0"></i>
                            <div class="truncate">
                                <span class="font-semibold text-slate-800">{{ customer.zone }}:</span>
                                <span class="text-slate-500 ml-1">{{ customer.address_summary }}</span>
                            </div>
                        </div>

                        <div v-if="customer.phone" class="text-xs text-slate-600 flex items-center gap-1.5">
                            <i class="fa-solid fa-phone text-slate-400 shrink-0"></i>
                            <span class="font-mono text-slate-700">{{ customer.phone }}</span>
                        </div>
                    </div>

                    <!-- Acciones del Cliente -->
                    <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                        <Link
                            :href="route('repartidor.direct-service.create', { customer_id: customer.id })"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-blue-900 text-white hover:bg-blue-800 transition min-h-[44px]"
                        >
                            <i class="fa-solid fa-plus text-sky-400 text-[11px]"></i>
                            <span>Registrar servicio</span>
                        </Link>

                        <Link
                            v-if="customer.pending_debt_amount > 0"
                            :href="route('repartidor.debt.index', { search: customer.nickname })"
                            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 transition min-h-[44px]"
                            title="Cobrar adeudo"
                        >
                            <i class="fa-solid fa-hand-holding-dollar text-amber-600"></i>
                            <span>Cobrar</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Paginación si existe -->
            <div v-if="customers.links && customers.links.length > 3" class="flex justify-center gap-1 pt-4">
                <Link
                    v-for="(link, idx) in customers.links"
                    :key="idx"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                        link.active ? 'bg-blue-900 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50',
                        !link.url ? 'opacity-40 pointer-events-none' : ''
                    ]"
                    v-html="link.label"
                />
            </div>

        </div>

        <!-- Barra inferior de navegación móvil para Repartidor -->
        <RepartidorBottomNav />
    </AuthenticatedLayout>
</template>

