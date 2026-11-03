<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    routes: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: '', date: '' }),
    },
});

const statusFilter = ref(props.filters.status || '');
const dateFilter = ref(props.filters.date || '');

const applyFilters = () => {
    router.get(route('admin.routes.index'), {
        status: statusFilter.value,
        date: dateFilter.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    statusFilter.value = '';
    dateFilter.value = '';
    applyFilters();
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'assigned':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'in_progress':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'completed':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'incident':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        case 'cancelled':
            return 'bg-slate-100 text-slate-800 border-slate-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};
</script>

<template>
    <Head title="Hojas de Ruta - Administración" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-route text-blue-900"></i> Hojas de Ruta de Distribución
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Programación, seguimiento y despacho de pedidos en ruta móvil
                    </p>
                </div>
                <div>
                    <Link
                        :href="route('admin.routes.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 transition shadow-2xs"
                    >
                        <i class="fa-solid fa-plus text-xs"></i> Nueva Hoja de Ruta
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Filtros de búsqueda -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Estado de ruta</label>
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                    >
                        <option value="">Todos los estados</option>
                        <option value="assigned">Asignada</option>
                        <option value="in_progress">En curso</option>
                        <option value="incident">Avería / Incidencia</option>
                        <option value="completed">Completada</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </div>

                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Fecha</label>
                    <input
                        v-model="dateFilter"
                        @change="applyFilters"
                        type="date"
                        class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                    />
                </div>

                <div>
                    <button
                        @click="resetFilters"
                        type="button"
                        class="w-full sm:w-auto px-4 py-2 rounded-xl text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 transition"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- Listado de rutas -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div v-if="routes.data.length === 0" class="p-8 text-center">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center text-xl mb-2">
                        <i class="fa-solid fa-route text-slate-400 text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-700">No se encontraron hojas de ruta</p>
                    <p class="text-xs text-slate-400 mt-1">Crea una nueva hoja de ruta con pedidos confirmados para asignarla al repartidor.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="px-5 py-3.5">Folio</th>
                                <th class="px-4 py-3.5">Fecha</th>
                                <th class="px-4 py-3.5">Chofer Responsable</th>
                                <th class="px-4 py-3.5">Paradas</th>
                                <th class="px-4 py-3.5">Estado</th>
                                <th class="px-4 py-3.5">Horarios</th>
                                <th class="px-5 py-3.5 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="route in routes.data"
                                :key="route.id"
                                class="hover:bg-slate-50/60 transition"
                            >
                                <td class="px-5 py-3.5 font-bold text-slate-900">
                                    {{ route.folio }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-600">
                                    {{ route.date }}
                                </td>
                                <td class="px-4 py-3.5 font-medium text-slate-800">
                                    {{ route.driver_name }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-extrabold text-slate-900 text-sm">{{ route.stops_count }}</span>
                                        <span class="text-slate-400 text-[10px]">
                                            ({{ route.pending_stops_count }} pend. • {{ route.completed_stops_count }} ok
                                            <template v-if="route.failed_stops_count > 0">
                                                • {{ route.failed_stops_count }} fallidas
                                            </template>)
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span
                                        class="px-2.5 py-1 rounded-lg font-bold text-[11px] border uppercase tracking-wider"
                                        :class="getStatusBadge(route.status)"
                                    >
                                        {{ route.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-slate-500 text-[11px]">
                                    <div v-if="route.started_at">Inició: {{ route.started_at }}</div>
                                    <div v-if="route.completed_at">Fin: {{ route.completed_at }}</div>
                                    <div v-if="!route.started_at">-</div>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link
                                        :href="route('admin.routes.show', route.id)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-blue-50 hover:text-blue-900 transition"
                                    >
                                        Ver Detalle
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="routes.links && routes.links.length > 3" class="p-4 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-1">
                        <template v-for="(link, i) in routes.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium"
                                :class="link.active ? 'bg-blue-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 text-xs text-slate-400"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

