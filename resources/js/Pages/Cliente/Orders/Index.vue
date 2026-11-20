<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    RefreshCw,
    Truck,
    Droplets,
    Package,
    MapPin,
    ChevronRight,
    Clock,
} from 'lucide-vue-next';

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
        default: null,
    },
    last_updated_at: {
        type: String,
        default: '',
    },
});

const selectedStatus = ref(props.filters.status || 'all');
const isRefreshing = ref(false);

const filterTabs = [
    { key: 'all', label: 'Todos', activeClass: 'bg-[#1E1545] text-white shadow-sm' },
    { key: 'pending', label: 'Pendientes', activeClass: 'bg-amber-600 text-white shadow-sm' },
    { key: 'delivered', label: 'Entregados', activeClass: 'bg-emerald-600 text-white shadow-sm' },
    { key: 'cancelled', label: 'Cancelados', activeClass: 'bg-rose-600 text-white shadow-sm' },
];

const applyStatusFilter = (status) => {
    selectedStatus.value = status;
    router.get(
        route('cliente.orders.index'),
        { status },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const refreshList = () => {
    isRefreshing.value = true;
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

const getStageBadge = (order) => {
    const key = order.operational_progress?.overall_stage_key || order.status;
    if (key === 'cancelado' || order.status === 'cancelled') {
        return {
            label: order.operational_progress?.overall_stage_label || order.status_label,
            classes: 'bg-rose-50 text-rose-700',
        };
    }
    if (key === 'entregado') {
        return {
            label: order.operational_progress?.overall_stage_label || order.status_label,
            classes: 'bg-emerald-50 text-emerald-700',
        };
    }
    if (key === 'en_reparticion' || key === 'asignado_a_ruta') {
        return {
            label: order.operational_progress?.overall_stage_label || order.status_label,
            classes: 'bg-sky-50 text-sky-800',
        };
    }
    if (
        key === 'confirmado' ||
        key === 'listo_para_entregar' ||
        key === 'lavando' ||
        key === 'llevando_a_planta' ||
        order.status === 'confirmed'
    ) {
        return {
            label: order.operational_progress?.overall_stage_label || order.status_label,
            classes: 'bg-violet-50 text-[#6D28D9]',
        };
    }
    return {
        label: order.operational_progress?.overall_stage_label || order.status_label,
        classes: 'bg-amber-50 text-amber-800',
    };
};

const getBottleSummary = (order) => {
    const fixed = Number(order.fixed_quantity || 0);
    const exchange = Number(order.exchange_quantity || 0);
    const total = Number(order.total_bottles || 0);

    if (fixed > 0 && exchange > 0) {
        return { isFixed: false, text: `${fixed} Fijos + ${exchange} de Cambio` };
    }
    if (fixed > 0) {
        return { isFixed: true, text: `${fixed} ${fixed === 1 ? 'botellón Fijo' : 'botellones Fijos'}` };
    }
    const qty = exchange || total;
    return { isFixed: false, text: `${qty} ${qty === 1 ? 'botellón de Cambio' : 'botellones de Cambio'}` };
};

const cleanPaginationLabel = (label) => {
    return String(label)
        .replace(/&laquo;\s*Previous/i, 'Anterior')
        .replace(/Next\s*&raquo;/i, 'Siguiente')
        .replace(/&laquo;/g, '‹')
        .replace(/&raquo;/g, '›');
};

const ordersList = computed(() => props.orders?.data ?? []);
const totalOrdersCount = computed(() => props.orders?.total ?? ordersList.value.length);
</script>

<template>
    <Head title="Mis pedidos" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 sm:space-y-5">
                <!-- ========================================== -->
                <!-- 1. ENCABEZADO MORADO MINIMALISTA           -->
                <!-- ========================================== -->
                <header class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1E1545] via-[#2A1E5E] to-[#3B297D] text-white shadow-md p-5 sm:p-6 border border-[#34266E]">
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute -right-10 -top-10 w-44 h-44 rounded-full bg-violet-400/15 blur-2xl"
                    ></div>

                    <div class="relative z-10 flex items-center justify-between gap-4">
                        <div class="space-y-0.5 min-w-0">
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                                Mis pedidos
                            </h1>
                            <p class="text-xs sm:text-sm text-violet-200 tabular-nums">
                                {{ totalOrdersCount === 1 ? '1 pedido registrado' : `${totalOrdersCount} pedidos registrados` }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <Link
                                v-if="!activeOrder"
                                :href="route('cliente.orders.create')"
                                class="hidden sm:inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] rounded-2xl text-sm font-extrabold text-[#1E1545] bg-white hover:bg-violet-50 shadow-sm transition"
                            >
                                <Droplets class="w-4 h-4 text-[#6D28D9]" aria-hidden="true" />
                                <span>Pedir botellones</span>
                            </Link>

                            <Link
                                v-else
                                :href="route('cliente.orders.show', activeOrder.id)"
                                class="hidden sm:inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] rounded-2xl text-sm font-extrabold text-[#1E1545] bg-white hover:bg-violet-50 shadow-sm transition"
                            >
                                <Truck class="w-4 h-4 text-[#6D28D9]" aria-hidden="true" />
                                <span>Ver pedido activo</span>
                            </Link>

                            <button
                                type="button"
                                @click="refreshList"
                                :disabled="isRefreshing"
                                aria-label="Actualizar listado de pedidos"
                                title="Actualizar"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-2xl text-white bg-white/15 hover:bg-white/25 border border-white/20 transition cursor-pointer disabled:opacity-60"
                            >
                                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </header>

                <!-- ========================================== -->
                <!-- 2. FILTROS SEGMENTADOS LIMPIOS             -->
                <!-- ========================================== -->
                <div
                    class="rounded-2xl bg-white border border-[#E9E7F0] p-1 shadow-sm grid grid-cols-4 gap-1 sm:inline-grid sm:w-auto"
                    role="group"
                    aria-label="Filtrar pedidos por estado"
                >
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.key"
                        type="button"
                        @click="applyStatusFilter(tab.key)"
                        :aria-pressed="selectedStatus === tab.key"
                        class="min-w-0 px-1.5 sm:px-4 py-2.5 min-h-[40px] rounded-xl text-[11px] sm:text-xs font-extrabold text-center truncate transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                        :class="selectedStatus === tab.key
                            ? tab.activeClass
                            : 'text-slate-600 hover:bg-[#F5F4F8] hover:text-[#1E1545]'"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- 3. LISTADO MINIMALISTA SIN LÍNEAS LATERALES-->
                <!-- ========================================== -->
                <section v-if="ordersList.length > 0" aria-label="Listado de pedidos">
                    <!-- Escritorio -->
                    <div class="hidden md:block rounded-2xl bg-white border border-[#E9E7F0] shadow-sm overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-[#F5F4F8]/80 border-b border-[#E9E7F0] text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3.5 px-5">Folio y fecha</th>
                                    <th class="py-3.5 px-5">Estado</th>
                                    <th class="py-3.5 px-5">Botellones</th>
                                    <th class="py-3.5 px-5">Entrega</th>
                                    <th class="py-3.5 px-5 text-right">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E9E7F0]">
                                <tr v-for="order in ordersList" :key="order.id" class="hover:bg-[#F5F4F8]/50 transition">
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-extrabold text-[#1E1545] tabular-nums">
                                                {{ order.folio }}
                                            </span>
                                            <span v-if="order.is_active" class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#ECE8F8] text-[#1E1545]">
                                                En curso
                                            </span>
                                        </div>
                                        <span class="text-xs text-slate-500 flex items-center gap-1 mt-1 tabular-nums">
                                            <Clock class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" />
                                            {{ order.created_at }}
                                        </span>
                                    </td>

                                    <td class="py-4 px-5">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold"
                                            :class="getStageBadge(order).classes"
                                        >
                                            {{ getStageBadge(order).label }}
                                        </span>
                                    </td>

                                    <td class="py-4 px-5 text-xs font-bold text-[#1E1545]">
                                        <span class="inline-flex items-center gap-1.5">
                                            <Package v-if="getBottleSummary(order).isFixed" class="w-4 h-4 text-indigo-600" aria-hidden="true" />
                                            <Droplets v-else class="w-4 h-4 text-[#0275B8]" aria-hidden="true" />
                                            <span>{{ getBottleSummary(order).text }}</span>
                                        </span>
                                    </td>

                                    <td class="py-4 px-5 text-xs text-slate-600">
                                        <div class="flex items-start gap-1.5">
                                            <MapPin class="w-3.5 h-3.5 text-emerald-600 mt-0.5 shrink-0" aria-hidden="true" />
                                            <div>
                                                <span class="font-semibold text-slate-800 block">{{ order.delivery_street }}</span>
                                                <span v-if="order.delivery_zone_label" class="text-[11px] text-slate-500">
                                                    {{ order.delivery_zone_label }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5 text-right">
                                        <Link
                                            :href="route('cliente.orders.show', order.id)"
                                            class="inline-flex items-center justify-center gap-1 px-3.5 py-2 min-h-[44px] rounded-xl text-xs font-bold text-[#1E1545] bg-[#F5F4F8] hover:bg-[#ECE8F8] border border-[#E9E7F0] transition"
                                        >
                                            <span>Ver</span>
                                            <ChevronRight class="w-4 h-4 text-[#6D28D9]" aria-hidden="true" />
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Móvil: tarjetas minimalistas limpias -->
                    <div class="grid grid-cols-1 gap-3 md:hidden">
                        <Link
                            v-for="order in ordersList"
                            :key="order.id"
                            :href="route('cliente.orders.show', order.id)"
                            class="block rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-4 space-y-3 active:scale-[0.99] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono text-sm font-extrabold text-[#1E1545] tabular-nums">
                                        {{ order.folio }}
                                    </span>
                                    <span v-if="order.is_active" class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#ECE8F8] text-[#1E1545]">
                                        En curso
                                    </span>
                                </div>

                                <span
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold shrink-0"
                                    :class="getStageBadge(order).classes"
                                >
                                    {{ getStageBadge(order).label }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-2.5 border-t border-[#E9E7F0]">
                                <div class="space-y-0.5 min-w-0">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1E1545]">
                                        <Package v-if="getBottleSummary(order).isFixed" class="w-3.5 h-3.5 text-indigo-600 shrink-0" aria-hidden="true" />
                                        <Droplets v-else class="w-3.5 h-3.5 text-[#0275B8] shrink-0" aria-hidden="true" />
                                        <span class="truncate">{{ getBottleSummary(order).text }}</span>
                                    </span>
                                    <span class="text-[11px] text-slate-500 flex items-center gap-1 tabular-nums">
                                        <Clock class="w-3 h-3 text-slate-400" aria-hidden="true" />
                                        {{ order.created_at }}
                                    </span>
                                </div>

                                <ChevronRight class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" />
                            </div>
                        </Link>
                    </div>

                    <!-- Paginación -->
                    <nav
                        v-if="orders.links && orders.links.length > 3"
                        aria-label="Paginación de pedidos"
                        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white border border-[#E9E7F0] px-4 py-3 shadow-sm"
                    >
                        <span class="text-xs text-slate-600 tabular-nums">
                            <strong>{{ orders.from }}</strong> - <strong>{{ orders.to }}</strong> de <strong>{{ orders.total }}</strong>
                        </span>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <template v-for="(link, idx) in orders.links" :key="idx">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    :aria-current="link.active ? 'page' : undefined"
                                    class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] px-3 rounded-xl text-xs font-bold border transition"
                                    :class="link.active
                                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                        : 'bg-white text-slate-700 border-[#E9E7F0] hover:bg-[#F5F4F8]'"
                                >
                                    {{ cleanPaginationLabel(link.label) }}
                                </Link>
                                <span
                                    v-else
                                    class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] px-3 rounded-xl text-xs font-semibold text-slate-400 bg-slate-50 border border-slate-200/70"
                                >
                                    {{ cleanPaginationLabel(link.label) }}
                                </span>
                            </template>
                        </div>
                    </nav>
                </section>

                <!-- Estado vacío limpio -->
                <section v-else class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-7 sm:p-10 text-center space-y-4">
                    <div class="mx-auto h-14 w-14 rounded-2xl bg-gradient-to-br from-violet-100 to-sky-100 text-[#6D28D9] flex items-center justify-center">
                        <Droplets class="w-7 h-7" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-sm mx-auto">
                        <h2 class="text-base sm:text-lg font-extrabold text-[#1E1545]">
                            {{ selectedStatus === 'all' ? 'Aún no tienes pedidos' : 'Sin pedidos en esta categoría' }}
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            {{ selectedStatus === 'all'
                                ? 'Cuando solicites tus botellones de agua purificada podrás seguir cada etapa desde aquí.'
                                : 'Prueba seleccionando otra pestaña arriba o ver todos tus pedidos.' }}
                        </p>
                    </div>
                    <div class="pt-1 flex flex-wrap justify-center gap-3">
                        <button
                            v-if="selectedStatus !== 'all'"
                            type="button"
                            @click="applyStatusFilter('all')"
                            class="px-5 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-[#1E1545] bg-[#F5F4F8] hover:bg-[#ECE8F8] border border-[#E9E7F0] transition cursor-pointer"
                        >
                            Ver todos
                        </button>
                        <Link
                            v-else-if="!activeOrder"
                            :href="route('cliente.orders.create')"
                            class="inline-flex items-center gap-2 px-5 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition"
                        >
                            <Droplets class="w-4 h-4 text-sky-300" aria-hidden="true" />
                            <span>Hacer mi primer pedido</span>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
