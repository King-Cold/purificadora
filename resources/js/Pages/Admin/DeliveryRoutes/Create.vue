<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    driver: {
        type: Object,
        nullable: true,
    },
    configError: {
        type: String,
        nullable: true,
    },
    eligibleOrders: {
        type: Array,
        default: () => [],
    },
    defaultDate: {
        type: String,
        required: true,
    },
});

const form = useForm({
    date: props.defaultDate,
    notes: '',
    order_ids: [],
});

// Lista ordenada de pedidos seleccionados para las paradas
const selectedOrders = ref([]);

const toggleOrder = (order) => {
    const idx = selectedOrders.value.findIndex(o => o.id === order.id);
    if (idx >= 0) {
        selectedOrders.value.splice(idx, 1);
    } else {
        selectedOrders.value.push(order);
    }
};

const isSelected = (orderId) => {
    return selectedOrders.value.some(o => o.id === orderId);
};

const moveUp = (index) => {
    if (index > 0) {
        const item = selectedOrders.value.splice(index, 1)[0];
        selectedOrders.value.splice(index - 1, 0, item);
    }
};

const moveDown = (index) => {
    if (index < selectedOrders.value.length - 1) {
        const item = selectedOrders.value.splice(index, 1)[0];
        selectedOrders.value.splice(index + 1, 0, item);
    }
};

const removeSelected = (index) => {
    selectedOrders.value.splice(index, 1);
};

const submitRoute = () => {
    form.order_ids = selectedOrders.value.map(o => o.id);
    form.post(route('admin.routes.store'));
};

const getOpBadgeClass = (type) => {
    switch (type) {
        case 'intercambio':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'mixto':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'recoleccion_fijos':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'devolucion_fijos':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-800 border-slate-200';
    }
};
</script>

<template>
    <Head title="Nueva Hoja de Ruta - Administración" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-plus text-blue-900"></i> Nueva Hoja de Ruta
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500">
                        Selecciona pedidos confirmados y define el orden manual de atención para el chofer
                    </p>
                </div>
                <Link
                    :href="route('admin.routes.index')"
                    class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                >
                    Volver
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Error de Configuración de Chofer -->
            <div
                v-if="configError"
                class="p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 space-y-2 shadow-2xs"
            >
                <div class="flex items-center gap-2 text-base font-bold text-rose-800">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Error de Configuración Operativa
                </div>
                <p class="text-xs sm:text-sm text-rose-700">
                    {{ configError }}
                </p>
                <p class="text-xs text-rose-600">
                    La creación de rutas está bloqueada hasta que exista exactamente un único repartidor activo en el sistema.
                </p>
            </div>

            <!-- Información del Chofer Único Activo -->
            <div
                v-else-if="driver"
                class="p-4 sm:p-5 rounded-2xl bg-blue-50/70 border border-blue-200/80 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Chofer asignado automáticamente</span>
                    <h3 class="text-base font-extrabold text-blue-950 mt-0.5">
                        {{ driver.name }} <span class="text-xs font-normal text-blue-800">({{ driver.email }})</span>
                    </h3>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 self-start sm:self-auto">
                    <i class="fa-solid fa-truck text-blue-800"></i> Único Repartidor Activo
                </div>
            </div>

            <form @submit.prevent="submitRoute" class="space-y-6">
                <!-- Datos generales de ruta -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Fecha programada *</label>
                        <input
                            v-model="form.date"
                            type="date"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                            required
                        />
                        <p v-if="form.errors.date" class="text-xs text-rose-600 mt-1">{{ form.errors.date }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Notas u observaciones</label>
                        <input
                            v-model="form.notes"
                            type="text"
                            placeholder="Ej. Ruta matutina sector norte y centro"
                            class="w-full text-xs rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                        />
                        <p v-if="form.errors.notes" class="text-xs text-rose-600 mt-1">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <div v-if="form.errors.route || form.errors.order_ids" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                    {{ form.errors.route || form.errors.order_ids }}
                </div>

                <!-- Panel de Selección y Orden de Paradas -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Columna Izquierda: Pedidos Elegibles (7 cols) -->
                    <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden flex flex-col">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">
                                    Pedidos Elegibles
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Haz clic para agregar o quitar pedidos de la hoja de ruta
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                {{ eligibleOrders.length }} disponibles
                            </span>
                        </div>

                        <div v-if="eligibleOrders.length === 0" class="p-8 text-center text-xs text-slate-400">
                            No hay pedidos confirmados o en proceso elegibles para reparto.
                        </div>

                        <div v-else class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto">
                            <div
                                v-for="order in eligibleOrders"
                                :key="order.id"
                                @click="toggleOrder(order)"
                                class="p-4 cursor-pointer hover:bg-slate-50 transition flex items-start gap-3"
                                :class="isSelected(order.id) ? 'bg-blue-50/40' : ''"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isSelected(order.id)"
                                    @click.stop="toggleOrder(order)"
                                    class="mt-1 rounded text-blue-900 focus:ring-blue-900"
                                />

                                <div class="flex-1 space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900 text-sm">{{ order.folio }}</span>
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                                :class="getOpBadgeClass(order.operation_type)"
                                            >
                                                {{ order.operation_label }}
                                            </span>
                                        </div>
                                        <span class="text-xs font-extrabold text-blue-900">
                                            {{ order.total_bottles }} u.
                                        </span>
                                    </div>

                                    <div class="text-xs text-slate-700">
                                        <span class="font-bold text-slate-900">
                                            {{ order.customer_nickname ? `"${order.customer_nickname}"` : order.customer_name }}
                                        </span>
                                        <span class="text-slate-400 ml-1">({{ order.customer_code }})</span>
                                        <span v-if="order.customer_nickname && order.customer_name" class="text-slate-500 text-[11px] block">
                                            Nombre: {{ order.customer_name }}
                                        </span>
                                    </div>

                                    <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                        <span class="flex items-center gap-1"><i class="fa-solid fa-location-dot text-slate-400"></i> Zona: {{ order.delivery_zone }}</span>
                                        <span>•</span>
                                        <span class="truncate">{{ order.address_summary }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Paradas Seleccionadas y Orden Manual (5 cols) -->
                    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden flex flex-col">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">
                                    Orden Manual de Paradas
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Secuencia exacta para el chofer (1, 2, 3...)
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-800">
                                {{ selectedOrders.length }} paradas
                            </span>
                        </div>

                        <div v-if="selectedOrders.length === 0" class="p-8 text-center text-xs text-slate-400">
                            No has seleccionado ningún pedido aún.<br>
                            Selecciona los pedidos de la lista izquierda para agregarlos a la ruta.
                        </div>

                        <div v-else class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto">
                            <div
                                v-for="(order, index) in selectedOrders"
                                :key="order.id"
                                class="p-3.5 flex items-center justify-between gap-2 hover:bg-slate-50 transition"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="w-6 h-6 rounded-full bg-blue-900 text-white font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ index + 1 }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 text-xs truncate">
                                            {{ order.customer_nickname ? `"${order.customer_nickname}"` : order.customer_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 truncate">
                                            {{ order.folio }} • {{ order.total_bottles }} garrafones
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <button
                                        @click.prevent="moveUp(index)"
                                        :disabled="index === 0"
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-30 flex items-center justify-center text-xs font-bold"
                                        title="Subir parada"
                                    >
                                        <i class="fa-solid fa-chevron-up"></i>
                                    </button>
                                    <button
                                        @click.prevent="moveDown(index)"
                                        :disabled="index === selectedOrders.length - 1"
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-30 flex items-center justify-center text-xs font-bold"
                                        title="Bajar parada"
                                    >
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    <button
                                        @click.prevent="removeSelected(index)"
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center text-xs font-bold"
                                        title="Quitar"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                            <button
                                type="submit"
                                :disabled="form.processing || !!configError || selectedOrders.length === 0"
                                class="w-full py-3 rounded-xl text-sm font-bold bg-blue-900 text-white hover:bg-blue-800 active:scale-95 disabled:opacity-50 transition shadow-2xs"
                            >
                                {{ form.processing ? 'Creando hoja de ruta...' : `Crear Hoja de Ruta (${selectedOrders.length} paradas)` }}
                            </button>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </AuthenticatedLayout>
</template>

