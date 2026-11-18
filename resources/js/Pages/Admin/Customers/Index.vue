<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/AdminPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { confirmAction } from '@/utils/swal';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            access: 'all',
            zone: 'all',
        }),
    },
    zones: {
        type: Object,
        default: () => ({}),
    },
    totalCustomersCount: {
        type: Number,
        default: 0,
    },
});

const search = ref(props.filters.search || '');
const access = ref(props.filters.access || 'all');
const zone = ref(props.filters.zone || 'all');
const isLoading = ref(false);

let searchTimeout = null;

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('admin.customers.index'),
        {
            search: search.value || undefined,
            access: access.value !== 'all' ? access.value : undefined,
            zone: zone.value !== 'all' ? zone.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
        }
    );
};

// Debounce para búsqueda
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Filtros inmediatos
watch([access, zone], () => {
    applyFilters();
});

const resetFilters = () => {
    search.value = '';
    access.value = 'all';
    zone.value = 'all';
    applyFilters();
};

const hasActiveFilters = () => {
    return Boolean(
        search.value ||
        (access.value && access.value !== 'all') ||
        (zone.value && zone.value !== 'all')
    );
};

const getInitials = (customer) => {
    const text = (customer?.name && customer.name.trim()) || (customer?.nickname && customer.nickname.trim()) || 'C';
    const parts = text.split(' ').filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return text.substring(0, 2).toUpperCase();
};

const getZoneBadgeClass = (zoneLabel) => {
    if (!zoneLabel || zoneLabel === 'Sin zona asignada') {
        return 'bg-[#F4F3F8] text-[#747181] border-[#E9E7F0] font-medium';
    }
    if (zoneLabel === 'Otro municipio') {
        return 'bg-[#FCECEF] text-[#E82147] border-[#E82147]/20 font-semibold';
    }
    if (zoneLabel.includes('Norte')) {
        return 'bg-[#E9F3F8] text-[#0071BA] border-[#DCECF7] font-semibold';
    }
    if (zoneLabel.includes('Sur')) {
        return 'bg-[#E8F2EC] text-[#34785A] border-[#34785A]/25 font-semibold';
    }
    if (zoneLabel.includes('Este')) {
        return 'bg-[#F4F3F8] text-[#1E1545] border-[#DAD6E8] font-semibold';
    }
    if (zoneLabel.includes('Oeste')) {
        return 'bg-[#F8F0DF] text-[#A97827] border-[#A97827]/25 font-semibold';
    }
    return 'bg-[#E9F3F8] text-[#0071BA] border-[#DCECF7] font-semibold';
};

// Control de borrado de cliente con SweetAlert2
const confirmDelete = async (customer) => {
    const label = customer.nickname || customer.name || 'este cliente';
    const confirmed = await confirmAction({
        title: '¿Eliminar este cliente?',
        text: `Estás a punto de eliminar a "${label}". El cliente será dado de baja del catálogo.`,
        confirmText: 'Sí, borrar cliente',
        icon: 'warning',
        danger: true,
    });

    if (confirmed) {
        router.delete(route('admin.customers.destroy', customer.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <AdminPageHeader
                title="Directorio de Clientes"
                subtitle="Control de clientes, envases registrados y zonas de reparto."
                icon="fa-solid fa-address-book"
                accent="green"
                :badge-text="`${totalCustomersCount} registrados`"
                :breadcrumbs="[
                    { label: 'Administración', href: route('dashboard') },
                    { label: 'Clientes' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="route('admin.customers.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E1545] hover:bg-[#2B1F60] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-[0_1px_2px_rgba(30,21,69,0.12)] transition duration-150 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]/30"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Nuevo Cliente</span>
                    </Link>
                </template>
            </AdminPageHeader>
        </template>

        <div class="py-6 sm:py-8">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1850px] px-4 sm:px-6 lg:px-8 space-y-5">

                <!-- Barra de Búsqueda y Filtros -->
                <div class="bg-white rounded-2xl shadow-sm border border-[#E9E7F0] p-4 sm:p-5">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                        <!-- Buscador -->
                        <div class="md:col-span-5 relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#A6A3B2]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar por nombre, apodo, teléfono..."
                                class="block w-full pl-10 pr-4 py-2 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] placeholder-[#A6A3B2] transition"
                            />
                        </div>

                        <!-- Filtro por Zona de Reparto -->
                        <div class="md:col-span-3">
                            <select
                                v-model="zone"
                                class="block w-full py-2 px-3 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] font-medium transition"
                            >
                                <option value="all">Todas las zonas</option>
                                <option v-for="(label, key) in zones" :key="key" :value="key">
                                    {{ String(label).toLowerCase().startsWith('zona') ? label : `Zona ${label}` }}
                                </option>
                                <option value="no_zone">Sin zona asignada</option>
                                <option value="other_municipality">Otros municipios</option>
                            </select>
                        </div>

                        <!-- Filtro por Tipo de Acceso al Sistema -->
                        <div class="md:col-span-3">
                            <select
                                v-model="access"
                                class="block w-full py-2 px-3 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] font-medium transition"
                            >
                                <option value="all">Todos los accesos</option>
                                <option value="with_account">Con cuenta</option>
                                <option value="without_account">Sin cuenta</option>
                            </select>
                        </div>

                        <!-- Botón Limpiar Filtros -->
                        <div class="md:col-span-1 flex justify-end">
                            <button
                                v-if="hasActiveFilters()"
                                @click="resetFilters"
                                type="button"
                                class="h-9 w-9 inline-flex items-center justify-center text-[#747181] hover:text-[#E82147] rounded-xl hover:bg-[#FCECEF] transition"
                                title="Limpiar todos los filtros"
                                aria-label="Limpiar todos los filtros"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Resumen de resultados filtrados -->
                    <div class="mt-3.5 pt-3 border-t border-[#E9E7F0]/80 flex items-center justify-between text-xs text-[#747181]">
                        <div>
                            <span>Mostrando <strong class="text-[#292638] tabular-nums">{{ customers.total }}</strong> clientes encontrados</span>
                            <span v-if="hasActiveFilters()" class="text-[#0071BA] ml-1.5 font-medium">(filtros aplicados)</span>
                        </div>
                        <div v-if="isLoading" class="flex items-center gap-1.5 text-[#0071BA] font-medium">
                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span>Actualizando...</span>
                        </div>
                    </div>
                </div>

                <!-- Contenedor de Clientes: Tarjetas en móvil y Tabla en escritorio -->
                <div class="bg-white rounded-2xl shadow-sm border border-[#E9E7F0] overflow-hidden">

                    <!-- VISTA MÓVIL (PANTALLAS < MD): TARJETAS COMPACTAS -->
                    <div class="md:hidden divide-y divide-[#E9E7F0]">
                        <div
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="p-4 space-y-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ getInitials(customer) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-semibold text-sm text-[#292638] leading-tight">
                                                {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                                            </span>
                                            <span v-if="customer.name && customer.nickname" class="text-[#1E1545] bg-[#F4F3F8] border border-[#DAD6E8] px-1.5 py-0.5 rounded-md text-[10px] font-semibold">
                                                "{{ customer.nickname }}"
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border"
                                                :class="customer.has_account ? 'bg-[#E8F2EC] text-[#34785A] border-[#34785A]/20' : 'bg-[#F8F0DF] text-[#A97827] border-[#A97827]/25'"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full" :class="customer.has_account ? 'bg-[#34785A]' : 'bg-[#A97827]'"></span>
                                                {{ customer.has_account ? 'Con cuenta' : 'Sin cuenta' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="customer.phone" class="text-right shrink-0">
                                    <a :href="`tel:${customer.phone}`" class="text-xs font-semibold text-[#0071BA] hover:underline tabular-nums">
                                        <i class="fa-solid fa-phone text-[10px] mr-1"></i>{{ customer.phone }}
                                    </a>
                                </div>
                            </div>

                            <!-- Botellones 20L y Domicilio -->
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div class="p-2.5 rounded-xl bg-[#F4F3F8]/70 border border-[#E9E7F0]">
                                    <span class="text-[10px] text-[#747181] font-semibold uppercase tracking-wide block">Botellones (20L)</span>
                                    <div class="mt-1">
                                        <span
                                            v-if="customer.bottle_type === 'fixed'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#F4F3F8] text-[#1E1545] border border-[#DAD6E8] tabular-nums"
                                        >
                                            <i class="fa-solid fa-tag text-[9px]"></i>
                                            Fijos: {{ customer.fixed_bottles_count }}
                                        </span>
                                        <span
                                            v-else-if="customer.bottle_type === 'exchange'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#E9F3F8] text-[#0071BA] border border-[#DCECF7] tabular-nums"
                                        >
                                            <i class="fa-solid fa-arrows-rotate text-[9px]"></i>
                                            Cambios: {{ customer.exchange_bottles_count }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#F8F0DF] text-[#A97827] border border-[#A97827]/25"
                                        >
                                            Sin botellones
                                        </span>
                                    </div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-[#F4F3F8]/70 border border-[#E9E7F0]">
                                    <span class="text-[10px] text-[#747181] font-semibold uppercase tracking-wide block">Zona / Ubicación</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] border mt-1"
                                        :class="getZoneBadgeClass(customer.zone_label)"
                                    >
                                        {{ customer.zone_label }}
                                    </span>
                                    <span class="text-[10px] text-[#747181] truncate block mt-1">{{ customer.main_address || 'Sin domicilio' }}</span>
                                </div>
                            </div>

                            <!-- Acciones en móvil -->
                            <div class="flex items-center justify-end gap-1.5 pt-2 border-t border-[#E9E7F0]/70 flex-wrap">
                                <Link
                                    :href="route('admin.customers.show', customer.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-[#0071BA] bg-[#E9F3F8] hover:bg-[#DCECF7] transition"
                                >
                                    <i class="fa-solid fa-eye text-[11px]"></i>
                                    <span>Ver</span>
                                </Link>
                                <Link
                                    :href="route('admin.customers.edit', customer.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-[#292638] bg-[#F4F3F8] hover:bg-[#E9E7F0] transition"
                                >
                                    <i class="fa-solid fa-pen text-[11px]"></i>
                                    <span>Editar</span>
                                </Link>
                                <button
                                    type="button"
                                    @click="confirmDelete(customer)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-[#E82147] bg-[#FCECEF] hover:bg-[#FCECEF]/80 transition cursor-pointer"
                                    title="Borrar cliente"
                                >
                                    <i class="fa-solid fa-trash-can text-[11px]"></i>
                                    <span>Borrar</span>
                                </button>
                            </div>
                        </div>

                        <!-- Estado vacío en móvil -->
                        <div v-if="customers.data.length === 0" class="p-10 text-center">
                            <div class="mx-auto h-12 w-12 text-[#747181] flex items-center justify-center rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] mb-3">
                                <i class="fa-solid fa-users-slash text-lg"></i>
                            </div>
                            <h4 class="text-sm font-semibold text-[#292638]">No se encontraron clientes</h4>
                            <p class="text-xs text-[#747181] mt-1">No hay registros con los filtros seleccionados.</p>
                            <button
                                v-if="hasActiveFilters()"
                                @click="resetFilters"
                                type="button"
                                class="mt-4 inline-flex items-center px-3.5 py-1.5 rounded-xl text-xs font-semibold text-[#1E1545] bg-[#F4F3F8] hover:bg-[#DAD6E8] transition cursor-pointer"
                            >
                                Restablecer filtros
                            </button>
                        </div>
                    </div>

                    <!-- VISTA ESCRITORIO (PANTALLAS >= MD): TABLA -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#E9E7F0] text-left">
                            <thead class="bg-[#F4F3F8] text-[11px] uppercase tracking-wider text-[#747181] font-semibold">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-6 pr-4">Cliente Particular</th>
                                    <th scope="col" class="px-4 py-3.5">Contacto</th>
                                    <th scope="col" class="px-4 py-3.5">Botellones (20L)</th>
                                    <th scope="col" class="px-4 py-3.5">Domicilio y Zona</th>
                                    <th scope="col" class="px-4 py-3.5">Acceso</th>
                                    <th scope="col" class="relative py-3.5 pl-4 pr-6 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E9E7F0] bg-white text-sm">
                                <tr
                                    v-for="customer in customers.data"
                                    :key="customer.id"
                                    class="hover:bg-[#F4F3F8]/60 transition-colors duration-150"
                                >
                                    <!-- Cliente: Avatar, Nombre y Apodo -->
                                    <td class="py-3.5 pl-6 pr-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-[#1E1545] text-white flex items-center justify-center font-bold text-xs tracking-wide shrink-0">
                                                {{ getInitials(customer) }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-semibold text-[#292638] leading-tight">
                                                        {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                                                    </span>
                                                    <span v-if="customer.name && customer.nickname" class="text-[#1E1545] bg-[#F4F3F8] border border-[#DAD6E8] px-1.5 py-0.5 rounded-md text-[10px] font-semibold">
                                                        "{{ customer.nickname }}"
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contacto: Teléfono y Correo -->
                                    <td class="px-4 py-3.5">
                                        <div class="text-xs text-[#292638] font-medium tabular-nums">
                                            {{ customer.phone || '—' }}
                                        </div>
                                        <div v-if="customer.email" class="text-[11px] text-[#747181] mt-0.5 truncate max-w-xs" :title="customer.email">
                                            {{ customer.email }}
                                        </div>
                                    </td>

                                    <!-- Botellones (Modalidad exclusiva de 20L) -->
                                    <td class="whitespace-nowrap px-4 py-3.5 text-xs">
                                        <span
                                            v-if="customer.bottle_type === 'fixed'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#F4F3F8] text-[#1E1545] border border-[#DAD6E8] tabular-nums"
                                        >
                                            <i class="fa-solid fa-tag text-[10px]"></i>
                                            <span>Fijos: {{ customer.fixed_bottles_count }}</span>
                                        </span>
                                        <span
                                            v-else-if="customer.bottle_type === 'exchange'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#E9F3F8] text-[#0071BA] border border-[#DCECF7] tabular-nums"
                                        >
                                            <i class="fa-solid fa-arrows-rotate text-[10px]"></i>
                                            <span>Cambios: {{ customer.exchange_bottles_count }}</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#F8F0DF] text-[#A97827] border border-[#A97827]/25"
                                        >
                                            Sin botellones
                                        </span>
                                    </td>

                                    <!-- Domicilio y Zona -->
                                    <td class="px-4 py-3.5 text-xs text-[#747181] max-w-xs md:max-w-sm lg:max-w-md xl:max-w-xl">
                                        <div v-if="customer.has_address" class="space-y-1">
                                            <div class="flex items-start gap-1.5">
                                                <i class="fa-solid fa-location-dot text-[11px] text-[#A6A3B2] mt-0.5 shrink-0"></i>
                                                <span class="line-clamp-2 text-xs text-[#292638]" :title="customer.main_address">
                                                    {{ customer.main_address }}
                                                </span>
                                            </div>
                                            <div class="pl-4">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] border"
                                                    :class="getZoneBadgeClass(customer.zone_label)"
                                                >
                                                    {{ customer.zone_label }}
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-[#A6A3B2] italic">
                                            Sin domicilio registrado
                                        </div>
                                    </td>

                                    <!-- Tipo de Acceso (Con cuenta / Sin cuenta) -->
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <span
                                            v-if="customer.has_account"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#E8F2EC] text-[#34785A] border border-[#34785A]/20"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#34785A]"></span>
                                            <span>Con cuenta</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#F8F0DF] text-[#A97827] border border-[#A97827]/25"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#A97827]"></span>
                                            <span>Sin cuenta</span>
                                        </span>
                                    </td>

                                    <!-- Columna Acciones -->
                                    <td class="whitespace-nowrap py-3.5 pl-4 pr-6 text-right text-xs">
                                        <div class="inline-flex items-center justify-end gap-1">
                                            <!-- Ver detalles -->
                                            <Link
                                                :href="route('admin.customers.show', customer.id)"
                                                class="h-8 w-8 inline-flex items-center justify-center text-[#747181] hover:text-[#0071BA] hover:bg-[#E9F3F8] rounded-lg transition"
                                                title="Ver detalles del cliente"
                                                aria-label="Ver detalles del cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>

                                            <!-- Editar -->
                                            <Link
                                                :href="route('admin.customers.edit', customer.id)"
                                                class="h-8 w-8 inline-flex items-center justify-center text-[#747181] hover:text-[#1E1545] hover:bg-[#F4F3F8] rounded-lg transition"
                                                title="Editar datos del cliente"
                                                aria-label="Editar datos del cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </Link>

                                            <!-- Borrar -->
                                            <button
                                                type="button"
                                                @click="confirmDelete(customer)"
                                                class="h-8 w-8 inline-flex items-center justify-center text-[#747181] hover:text-[#E82147] hover:bg-[#FCECEF] rounded-lg transition cursor-pointer"
                                                title="Borrar cliente"
                                                aria-label="Borrar cliente"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Estado Vacío en tabla -->
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="6" class="px-6 py-14 text-center">
                                        <div class="max-w-xs mx-auto">
                                            <div class="mx-auto h-12 w-12 text-[#747181] flex items-center justify-center rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] mb-3">
                                                <i class="fa-solid fa-users-slash text-lg"></i>
                                            </div>
                                            <h4 class="text-sm font-semibold text-[#292638]">No se encontraron clientes</h4>
                                            <p class="text-xs text-[#747181] mt-1">
                                                No existen clientes particulares que coincidan con los filtros aplicados.
                                            </p>
                                            <button
                                                v-if="hasActiveFilters()"
                                                @click="resetFilters"
                                                type="button"
                                                class="mt-4 inline-flex items-center px-3.5 py-1.5 text-xs font-semibold rounded-xl text-[#1E1545] bg-[#F4F3F8] hover:bg-[#DAD6E8] transition cursor-pointer"
                                            >
                                                Restablecer filtros
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div
                        v-if="customers.total > 0"
                        class="bg-[#F4F3F8]/40 px-4 py-3.5 sm:px-6 border-t border-[#E9E7F0] flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div class="text-xs text-[#747181] tabular-nums">
                            Mostrando del <span class="font-semibold text-[#292638]">{{ customers.from || 0 }}</span> al <span class="font-semibold text-[#292638]">{{ customers.to || 0 }}</span> de <span class="font-semibold text-[#292638]">{{ customers.total }}</span> clientes
                        </div>

                        <div v-if="customers.links && customers.links.length > 3" class="flex flex-wrap gap-1.5 items-center">
                            <template v-for="(link, key) in customers.links" :key="key">
                                <span
                                    v-if="link.url === null"
                                    class="px-3 py-1.5 text-xs text-[#A6A3B2] bg-white/60 border border-[#E9E7F0] rounded-lg cursor-not-allowed tabular-nums"
                                    v-html="link.label"
                                />
                                <Link
                                    v-else
                                    :href="link.url"
                                    preserve-scroll
                                    preserve-state
                                    class="px-3 py-1.5 text-xs rounded-lg border transition font-medium tabular-nums"
                                    :class="link.active ? 'bg-[#1E1545] text-white border-[#1E1545] shadow-sm' : 'bg-white text-[#292638] border-[#E9E7F0] hover:bg-[#F4F3F8]'"
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
