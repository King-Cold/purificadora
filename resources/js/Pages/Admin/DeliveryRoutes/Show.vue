<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    route: {
        type: Object,
        required: true,
    },
});

const resumeForm = useForm({
    notes: '',
});

const submitResume = () => {
    if (confirm('¿Confirmas que la avería o incidencia fue resuelta y deseas reanudar la ruta?')) {
        resumeForm.post(route('admin.routes.resume', props.route.id));
    }
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

const getStopStatusBadge = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        case 'completed':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'failed':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};
</script>

<template>
    <Head :title="`Ruta ${route.folio} - Administración`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-slate-800">
                            Hoja de Ruta {{ route.folio }}
                        </h2>
                        <span
                            class="px-2.5 py-1 rounded-lg text-xs font-bold border uppercase tracking-wider"
                            :class="getStatusBadge(route.status)"
                        >
                            {{ route.status_label }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        Fecha programada: {{ route.date }} • Chofer: <span class="font-semibold text-slate-700">{{ route.driver.name }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="route.status === 'incident'"
                        @click="submitResume"
                        :disabled="resumeForm.processing"
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition shadow-2xs inline-flex items-center gap-1.5"
                    >
                        <i class="fa-solid fa-arrows-rotate"></i> Reanudar Ruta
                    </button>
                    <Link
                        :href="route('admin.routes.index')"
                        class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                    >
                        Volver al listado
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Banner de avería / incidencia si aplica -->
            <div
                v-if="route.status === 'incident'"
                class="p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-2xs space-y-1"
            >
                <div class="flex items-center gap-2 font-bold text-sm text-rose-800">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Ruta en Estado de Avería / Incidencia
                </div>
                <p class="text-xs text-rose-700">
                    {{ route.notes }}
                </p>
                <p class="text-xs text-rose-600 font-medium">
                    Las paradas no atendidas se conservan pendientes para reanudar el recorrido en cuanto el vehículo esté listo.
                </p>
            </div>

            <!-- Resumen de Paradas -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                    <span class="text-xs text-slate-500 font-bold uppercase">Total Paradas</span>
                    <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ route.stops.length }}</div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
                    <span class="text-xs text-slate-500 font-bold uppercase">Pendientes</span>
                    <div class="text-2xl font-extrabold text-slate-700 mt-1">
                        {{ route.stops.filter(s => s.status === 'pending').length }}
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-2xs">
                    <span class="text-xs text-emerald-600 font-bold uppercase">Atendidas</span>
                    <div class="text-2xl font-extrabold text-emerald-900 mt-1">
                        {{ route.stops.filter(s => s.status === 'completed').length }}
                    </div>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-rose-100 shadow-2xs">
                    <span class="text-xs text-rose-600 font-bold uppercase">Visitas Fallidas</span>
                    <div class="text-2xl font-extrabold text-rose-900 mt-1">
                        {{ route.stops.filter(s => s.status === 'failed').length }}
                    </div>
                </div>
            </div>

            <!-- Detalle de Paradas Secuenciales -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">
                            Secuencia de Paradas
                        </h3>
                        <p class="text-xs text-slate-500">
                            Orden asignado para el recorrido
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    <div
                        v-for="stop in route.stops"
                        :key="stop.id"
                        class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/60 transition"
                    >
                        <div class="flex items-start gap-3.5 min-w-0">
                            <span class="w-8 h-8 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shrink-0 mt-0.5">
                                {{ stop.stop_number }}
                            </span>

                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-900 text-sm">
                                        {{ stop.customer_nickname ? `"${stop.customer_nickname}"` : stop.customer_name }}
                                    </span>
                                    <span class="text-xs text-slate-400 font-mono">({{ stop.customer_code }})</span>
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                        :class="getStopStatusBadge(stop.status)"
                                    >
                                        {{ stop.status_label }}
                                    </span>
                                </div>

                                <div v-if="stop.customer_nickname && stop.customer_name" class="text-xs text-slate-500">
                                    Nombre registrado: {{ stop.customer_name }}
                                </div>

                                <div class="text-xs text-slate-600 flex items-center gap-2 flex-wrap">
                                    <span class="flex items-center gap-1"><i class="fa-solid fa-location-dot text-slate-400"></i> {{ stop.address_summary }}</span>
                                    <span>•</span>
                                    <span class="font-semibold text-blue-900">Zona: {{ stop.delivery_zone }}</span>
                                    <span v-if="stop.customer_phone">• Tel: {{ stop.customer_phone }}</span>
                                </div>

                                <div class="text-xs text-slate-500 flex items-center gap-2">
                                    <span class="font-semibold text-slate-700">{{ stop.operation_label }}:</span>
                                    <span>{{ stop.total_bottles }} garrafones ({{ stop.exchange_quantity }} cambio, {{ stop.fixed_quantity }} fijos)</span>
                                </div>

                                <!-- Motivo de visita fallida si aplica -->
                                <div v-if="stop.status === 'failed' && stop.failure_reason" class="text-xs text-rose-700 bg-rose-50 p-2 rounded-lg border border-rose-100">
                                    <span class="font-bold">Motivo de no entrega:</span> {{ stop.failure_reason }}
                                    <span v-if="stop.notes" class="block text-slate-500 text-[11px] mt-0.5">{{ stop.notes }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            <Link
                                v-if="stop.order_id"
                                :href="route('admin.orders.show', stop.order_id)"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition"
                            >
                                Ver Pedido {{ stop.order_folio }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

