<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Activity, Clock } from 'lucide-vue-next';

const props = defineProps({
    movements: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ type: 'all', date_from: '', date_to: '' }),
    },
});

const filterType = ref(props.filters.type || 'all');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');

const applyFilters = () => {
    router.get(route('planta.history.index'), {
        type: filterType.value,
        date_from: filterDateFrom.value || undefined,
        date_to: filterDateTo.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filterType.value = 'all';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    applyFilters();
};

const formatDate = (dateString) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const translateMovement = (type) => {
    const map = {
        plant_initial_count: { label: 'Conteo inicial', badge: 'bg-[#F4F3F8] text-[#1E1545]' },
        plant_physical_recount: { label: 'Ajuste / reinicio', badge: 'bg-[#E5F4FD] text-[#0071BA]' },
        plant_washing_started: { label: 'Lavado iniciado', badge: 'bg-[#E0F2FE] text-[#0284C7]' },
        plant_washing_finished: { label: 'Llenado en Planta', badge: 'bg-[#E6F7EE] text-[#26845B]' },
        truck_load_exchange: { label: 'Planta → Camión (Cambios)', badge: 'bg-[#E5F4FD] text-[#0071BA]' },
        truck_unload_exchange: { label: 'Camión → Planta (Cambios)', badge: 'bg-[#EEEAF7] text-[#1E1545]' },
        truck_load_fixed: { label: 'Planta → Camión (Fijos)', badge: 'bg-[#E5F4FD] text-[#0071BA]' },
        plant_reception_fixed: { label: 'Recepción de Fijos', badge: 'bg-[#EEEAF7] text-[#1E1545]' },
        plant_prepared_fixed: { label: 'Fijos listos', badge: 'bg-[#E6F7EE] text-[#26845B]' },
        exchange_new_entry: { label: 'Alta de nuevos', badge: 'bg-[#E6F7EE] text-[#26845B]' },
        exchange_write_off: { label: 'Baja confirmada', badge: 'bg-[#FCECEF] text-[#E82147]' },
        exchange_recovery: { label: 'Recuperación', badge: 'bg-[#E6F7EE] text-[#26845B]' },
        exchange_inconvenience_reported: { label: 'Sin localizar', badge: 'bg-[#FEF3C7] text-[#B45309]' },
        bottle_incident: { label: 'Incidencia', badge: 'bg-[#FEF3C7] text-[#B45309]' },
    };
    return map[type] || { label: type.replace(/_/g, ' '), badge: 'bg-[#F4F3F8] text-[#1E1545]' };
};
</script>

<template>
    <Head title="Historial - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <div class="bg-[#F5F4F8] min-h-full py-5 sm:py-7">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1600px] px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- ENCABEZADO -->
                <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center shadow-sm">
                            <Clock class="w-5 h-5" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-[#1E1545]">
                                Historial
                            </h1>
                            <p class="text-xs sm:text-sm text-[#747181]">
                                Movimientos de envases, cargas, descargas y ajustes en Planta
                            </p>
                        </div>
                    </div>

                    <span class="px-3 py-1.5 rounded-xl bg-white border border-[#E9E7F0] text-xs font-bold text-[#1E1545] tabular-nums self-start sm:self-auto">
                        {{ movements.length }} registros
                    </span>
                </header>

                <!-- FILTROS Y MOVIMIENTOS -->
                <section class="rounded-2xl border border-[#E9E7F0] bg-white p-5 sm:p-6 space-y-4">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-3 border-b border-[#F4F3F8]">
                        <div class="flex items-center gap-2">
                            <Activity class="w-4 h-4 text-[#0071BA]" />
                            <h2 class="text-base font-bold text-[#1E1545]">Bitácora cronológica</h2>
                        </div>

                        <form @submit.prevent="applyFilters" class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="filterType"
                                @change="applyFilters"
                                class="h-10 text-xs font-semibold rounded-xl border-[#E9E7F0] text-[#1E1545] focus:border-[#0071BA] focus:ring-[#0071BA]"
                            >
                                <option value="all">Todos los movimientos</option>
                                <option value="ajustes">Ajustes, reinicios y altas</option>
                                <option value="cargas">Subidas a Camión</option>
                                <option value="descargas">Recepciones en Planta</option>
                                <option value="llenado">Llenado en Planta</option>
                                <option value="incidencias">Incidencias y resoluciones</option>
                            </select>

                            <input v-model="filterDateFrom" type="date" @change="applyFilters" class="h-10 text-xs rounded-xl border-[#E9E7F0] text-[#1E1545]" />
                            <input v-model="filterDateTo" type="date" @change="applyFilters" class="h-10 text-xs rounded-xl border-[#E9E7F0] text-[#1E1545]" />

                            <button
                                v-if="filterType !== 'all' || filterDateFrom || filterDateTo"
                                type="button"
                                @click="clearFilters"
                                class="h-10 px-3 rounded-xl bg-[#F4F3F8] hover:bg-[#E9E7F0] text-[#1E1545] text-xs font-bold cursor-pointer"
                            >
                                Limpiar
                            </button>
                        </form>
                    </div>

                    <div v-if="movements.length === 0" class="py-10 text-center text-xs text-[#747181]">
                        No se encontraron movimientos con los filtros seleccionados.
                    </div>

                    <div v-else class="divide-y divide-[#F4F3F8]">
                        <div
                            v-for="mov in movements"
                            :key="mov.id"
                            class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs"
                        >
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold" :class="translateMovement(mov.movement_type).badge">
                                        {{ translateMovement(mov.movement_type).label }}
                                    </span>
                                    <span class="font-bold text-[#1E1545] tabular-nums">{{ mov.quantity }} u.</span>
                                    <span v-if="mov.customer" class="text-[#0071BA] font-semibold">
                                        · {{ mov.customer.nickname || mov.customer.name }}
                                    </span>
                                </div>
                                <p v-if="mov.notes" class="text-[#292638] leading-relaxed">{{ mov.notes }}</p>
                            </div>

                            <div class="text-[11px] text-[#747181] sm:text-right shrink-0 tabular-nums">
                                <div class="font-semibold text-[#1E1545]">{{ formatDate(mov.created_at) }}</div>
                                <div>{{ mov.confirmed_by_user?.name || mov.user?.name || 'Sistema' }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
