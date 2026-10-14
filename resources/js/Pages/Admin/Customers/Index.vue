<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    customers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
            access: 'all',
        }),
    },
    totalCustomersCount: {
        type: Number,
        default: 0,
    },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');
const access = ref(props.filters.access || 'all');
const isLoading = ref(false);

let searchTimeout = null;

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('admin.customers.index'),
        {
            search: search.value || undefined,
            status: status.value !== 'all' ? status.value : undefined,
            access: access.value !== 'all' ? access.value : undefined,
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
watch([status, access], () => {
    applyFilters();
});

const resetFilters = () => {
    search.value = '';
    status.value = 'all';
    access.value = 'all';
    applyFilters();
};

const hasActiveFilters = () => {
    return Boolean(search.value || (status.value && status.value !== 'all') || (access.value && access.value !== 'all'));
};

const getStatusBadgeClass = (customerStatus) => {
    switch (customerStatus) {
        case 'active':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'suspended':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'inactive':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const getStatusLabel = (customerStatus) => {
    switch (customerStatus) {
        case 'active':
            return 'Activo';
        case 'suspended':
            return 'Suspendido';
        case 'inactive':
            return 'Inactivo';
        default:
            return customerStatus;
    }
};

const getInitials = (name) => {
    if (!name) return 'C';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts.substring(0, 2).toUpperCase();
};
</script>

<template>
    <Head title="Directorio de Clientes - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            Directorio de Clientes
                        </h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ totalCustomersCount }} registrados
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Directorio y consulta de personas particulares suscritas a entrega de garrafones
                    </p>
                </div>

                <!-- Botón Nuevo Cliente Habilitado -->
                <div class="self-start sm:self-auto">
                    <Link
                        :href="route('admin.customers.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white rounded-lg text-sm font-medium hover:bg-blue-800 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Nuevo cliente</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Mensaje de Éxito Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm animate-fade-in"
                >
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium">{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- Mensaje de Advertencia Flash (Duplicados preventivos) -->
                <div
                    v-if="$page.props.flash?.warning"
                    class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center justify-between shadow-sm animate-fade-in"
                >
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium">{{ $page.props.flash.warning }}</span>
                    </div>
                </div>

                <!-- Mensaje de Error Flash -->
                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center justify-between shadow-sm animate-fade-in"
                >
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <span class="text-sm font-medium">{{ $page.props.flash.error }}</span>
                    </div>
                </div>

                <!-- Barra de Búsqueda y Filtros -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 sm:p-5">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <!-- Buscador -->
                        <div class="md:col-span-5 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Buscar por nombre, apodo, teléfono o código (CLI-XXXX)..."
                                class="block w-full pl-10 pr-4 py-2 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            />
                        </div>

                        <!-- Filtro por Estado Comercial -->
                        <div class="md:col-span-3">
                            <select
                                v-model="status"
                                class="block w-full py-2 px-3 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-700 font-medium"
                            >
                                <option value="all">Todos los estados</option>
                                <option value="active">Activo</option>
                                <option value="suspended">Suspendido</option>
                                <option value="inactive">Inactivo</option>
                            </select>
                        </div>

                        <!-- Filtro por Tipo de Acceso al Sistema -->
                        <div class="md:col-span-3">
                            <select
                                v-model="access"
                                class="block w-full py-2 px-3 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-700 font-medium"
                            >
                                <option value="all">Cuentas y sin cuenta</option>
                                <option value="with_account">Con cuenta de usuario</option>
                                <option value="without_account">Sin cuenta de usuario</option>
                            </select>
                        </div>

                        <!-- Botón Limpiar Filtros -->
                        <div class="md:col-span-1 flex justify-end">
                            <button
                                v-if="hasActiveFilters()"
                                @click="resetFilters"
                                type="button"
                                class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition"
                                title="Limpiar todos los filtros"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Resumen de resultados filtrados -->
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <div>
                            <span>Mostrando <strong>{{ customers.total }}</strong> clientes encontrados</span>
                            <span v-if="hasActiveFilters()" class="text-blue-600 ml-1 font-medium">(filtros aplicados)</span>
                        </div>
                        <div v-if="isLoading" class="flex items-center gap-1.5 text-blue-600">
                            <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            <span>Actualizando...</span>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Clientes -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50/75 text-xs uppercase tracking-wider text-gray-500 font-semibold">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-6 pr-3">Cliente Particular</th>
                                    <th scope="col" class="px-3 py-3.5">Apodo</th>
                                    <th scope="col" class="px-3 py-3.5">Contacto</th>
                                    <th scope="col" class="px-3 py-3.5">Domicilio Principal</th>
                                    <th scope="col" class="px-3 py-3.5">Tipo de Acceso</th>
                                    <th scope="col" class="px-3 py-3.5">Estado</th>
                                    <th scope="col" class="px-3 py-3.5">Registro</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white text-sm">
                                <tr
                                    v-for="customer in customers.data"
                                    :key="customer.id"
                                    class="hover:bg-gray-50/80 transition duration-150"
                                >
                                    <!-- Cliente: Avatar, Nombre y Código -->
                                    <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-full bg-blue-900 text-white flex items-center justify-center font-bold text-xs tracking-wider shadow-sm shrink-0">
                                                {{ getInitials(customer.name) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 leading-tight">
                                                    {{ customer.name }}
                                                </div>
                                                <div class="text-xs font-mono font-medium text-sky-800 bg-sky-50 px-1.5 py-0.5 rounded w-fit mt-0.5">
                                                    {{ customer.code }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Apodo / Alias -->
                                    <td class="whitespace-nowrap px-3 py-4 text-xs font-medium">
                                        <span v-if="customer.nickname" class="text-blue-950 bg-blue-50/90 border border-blue-200 px-2 py-0.5 rounded-md font-semibold">
                                            "{{ customer.nickname }}"
                                        </span>
                                        <span v-else class="text-gray-400 font-normal">
                                            —
                                        </span>
                                    </td>

                                    <!-- Contacto: Teléfono y Correo -->
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <div class="text-xs text-gray-900 font-medium">
                                            {{ customer.phone }}
                                        </div>
                                        <div class="text-xs text-gray-500 font-mono mt-0.5 truncate max-w-[180px]">
                                            {{ customer.email }}
                                        </div>
                                    </td>

                                    <!-- Domicilio Principal -->
                                    <td class="px-3 py-4 text-xs text-gray-600 max-w-xs">
                                        <div class="flex items-start gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span class="line-clamp-2" :title="customer.main_address">
                                                {{ customer.main_address }}
                                            </span>
                                        </div>
                                        <span v-if="customer.addresses_count > 1" class="text-[10px] text-gray-400 mt-0.5 inline-block">
                                            +{{ customer.addresses_count - 1 }} domicilio(s) adicional(es)
                                        </span>
                                    </td>

                                    <!-- Tipo de Acceso (Con cuenta / Sin cuenta) -->
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <span
                                            v-if="customer.has_account"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-800 border border-sky-200"
                                        >
                                            <svg class="w-3 h-3 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            Con cuenta
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                            Sin cuenta
                                        </span>
                                    </td>

                                    <!-- Estado Comercial -->
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                            :class="getStatusBadgeClass(customer.status)"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="{
                                                'bg-emerald-500': customer.status === 'active',
                                                'bg-amber-500': customer.status === 'suspended',
                                                'bg-rose-500': customer.status === 'inactive',
                                            }"></span>
                                            {{ getStatusLabel(customer.status) }}
                                        </span>
                                    </td>

                                    <!-- Fecha de Registro -->
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-gray-500">
                                        {{ customer.created_at }}
                                    </td>

                                    <!-- Columna Acciones -->
                                    <td class="whitespace-nowrap py-4 pl-3 pr-6 text-right text-xs">
                                        <Link
                                            :href="route('admin.customers.show', customer.id)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-blue-900 bg-blue-50 hover:bg-blue-100 rounded-lg transition"
                                            title="Ver detalles del cliente"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Ver detalles</span>
                                        </Link>
                                    </td>
                                </tr>

                                <!-- Estado Vacío -->
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="max-w-xs mx-auto">
                                            <div class="mx-auto h-12 w-12 text-gray-400 flex items-center justify-center rounded-full bg-gray-100 mb-3">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                            <h4 class="text-sm font-semibold text-gray-900">No se encontraron clientes</h4>
                                            <p class="text-xs text-gray-500 mt-1">
                                                No existen clientes particulares que coincidan con los filtros aplicados.
                                            </p>
                                            <button
                                                v-if="hasActiveFilters()"
                                                @click="resetFilters"
                                                type="button"
                                                class="mt-4 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-blue-700 bg-blue-100 hover:bg-blue-200 transition"
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
                        class="bg-white px-4 py-3 sm:px-6 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div class="text-xs text-gray-600">
                            Mostrando del <span class="font-semibold">{{ customers.from || 0 }}</span> al <span class="font-semibold">{{ customers.to || 0 }}</span> de <span class="font-semibold">{{ customers.total }}</span> clientes
                        </div>

                        <div v-if="customers.links && customers.links.length > 3" class="flex flex-wrap gap-1 items-center">
                            <template v-for="(link, key) in customers.links" :key="key">
                                <span
                                    v-if="link.url === null"
                                    class="px-3 py-1.5 text-xs text-gray-400 border border-gray-200 rounded-lg cursor-not-allowed"
                                    v-html="link.label"
                                />
                                <Link
                                    v-else
                                    :href="link.url"
                                    preserve-scroll
                                    preserve-state
                                    class="px-3 py-1.5 text-xs rounded-lg border transition font-medium"
                                    :class="link.active ? 'bg-blue-900 text-white border-blue-900 shadow-sm' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
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
