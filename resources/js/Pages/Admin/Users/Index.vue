<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role: 'all',
            status: 'all',
        }),
    },
    availableRoles: {
        type: Array,
        default: () => [],
    },
    totalUsersCount: {
        type: Number,
        default: 0,
    },
});

const search = ref(props.filters.search || '');
const role = ref(props.filters.role || 'all');
const status = ref(props.filters.status || 'all');
const isLoading = ref(false);

let searchTimeout = null;

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('admin.users.index'),
        {
            search: search.value || undefined,
            role: role.value !== 'all' ? role.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
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

// Debounce para búsqueda por texto
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 350);
});

// Filtro inmediato al cambiar rol o estado
watch([role, status], () => {
    applyFilters();
});

const resetFilters = () => {
    search.value = '';
    role.value = 'all';
    status.value = 'all';
    applyFilters();
};

const hasActiveFilters = () => {
    return Boolean(search.value || (role.value && role.value !== 'all') || (status.value && status.value !== 'all'));
};

const getRoleBadgeClass = (roleName) => {
    switch (roleName) {
        case 'Super Admin':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Administrador':
            return 'bg-blue-50 text-blue-800 border-blue-200 font-semibold';
        case 'Repartidor':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'Operador Planta':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'Cliente':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        default:
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return 'U';
    const clean = name.trim();
    if (!clean) return 'U';
    const parts = clean.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return clean.slice(0, 2).toUpperCase();
};

const handleToggleStatus = (user) => {
    const isCurrentActive = user.is_active;
    const confirmMsg = isCurrentActive
        ? `¿Confirmas que deseas desactivar la cuenta de ${user.name}? Se revocará su acceso al sistema inmediatamente.`
        : `¿Deseas activar la cuenta de ${user.name}?`;

    if (confirm(confirmMsg)) {
        router.patch(route('admin.users.toggle-status', user.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Gestión de Usuarios - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Usuarios', icon: 'fa-solid fa-users' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            Gestión de Usuarios
                        </h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ totalUsersCount }} registrados
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1850px] px-4 sm:px-6 lg:px-8 space-y-6">

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
                                placeholder="Buscar por nombre o correo electrónico..."
                                class="block w-full pl-10 pr-4 py-2 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-400"
                            />
                        </div>

                        <!-- Filtro por Rol -->
                        <div class="md:col-span-3">
                            <select
                                v-model="role"
                                class="block w-full py-2 px-3 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-700 font-medium"
                            >
                                <option value="all">Todos los roles</option>
                                <option v-for="r in availableRoles" :key="r" :value="r">
                                    {{ r }}
                                </option>
                            </select>
                        </div>

                        <!-- Filtro por Estado -->
                        <div class="md:col-span-3">
                            <select
                                v-model="status"
                                class="block w-full py-2 px-3 text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-gray-700 font-medium"
                            >
                                <option value="all">Todos los estados</option>
                                <option value="active">Cuenta Activa</option>
                                <option value="inactive">Cuenta Inactiva</option>
                                <option value="verified">Correo Verificado</option>
                                <option value="unverified">Correo Pendiente</option>
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
                            <span>Mostrando <strong>{{ users.total }}</strong> usuarios encontrados</span>
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

                <!-- Tabla de Usuarios -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left">
                            <thead class="bg-gray-50/75 text-xs uppercase tracking-wider text-gray-500 font-semibold">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-6 pr-3">Usuario</th>
                                    <th scope="col" class="px-3 py-3.5">Correo Electrónico</th>
                                    <th scope="col" class="px-3 py-3.5">Rol de Acceso</th>
                                    <th scope="col" class="px-3 py-3.5">Estado</th>
                                    <th scope="col" class="px-3 py-3.5">Registro</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white text-sm">
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="hover:bg-gray-50/80 transition duration-150"
                                >
                                    <!-- Nombre y Avatar -->
                                    <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-full bg-blue-900 text-white flex items-center justify-center font-bold text-xs tracking-wider shadow-sm shrink-0">
                                                {{ getInitials(user.name) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 leading-tight">
                                                    {{ user.name }}
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                                                    <span>ID: #{{ user.id }}</span>
                                                    <span v-if="user.has_customer" class="text-sky-700 bg-sky-50 px-1.5 py-0.2 rounded font-mono text-[10px]">
                                                        {{ user.customer_code }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Correo -->
                                    <td class="whitespace-nowrap px-3 py-4 text-gray-600 font-mono text-xs">
                                        {{ user.email }}
                                    </td>

                                    <!-- Rol -->
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs border"
                                            :class="getRoleBadgeClass(user.primary_role)"
                                        >
                                            {{ user.primary_role }}
                                        </span>
                                    </td>

                                    <!-- Estado de Cuenta y Verificación -->
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                v-if="user.is_active"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 w-fit"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Activo
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 w-fit"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                                Inactivo
                                            </span>
                                            <span class="text-[11px] text-gray-400">
                                                {{ user.is_verified ? 'Email verificado' : 'Email pendiente' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fecha de Registro -->
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-gray-500">
                                        {{ user.created_at }}
                                    </td>

                                    <!-- Columna Acciones -->
                                    <td class="whitespace-nowrap py-4 pl-3 pr-6 text-right text-xs">
                                        <div class="flex items-center justify-end gap-1">
                                            <!-- Ver detalles -->
                                            <Link
                                                :href="route('admin.users.show', user.id)"
                                                class="p-1.5 text-gray-500 hover:text-blue-900 hover:bg-blue-50 rounded-lg transition"
                                                title="Ver detalles"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>

                                            <!-- Editar -->
                                            <Link
                                                :href="route('admin.users.edit', user.id)"
                                                class="p-1.5 text-gray-500 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg transition"
                                                title="Editar usuario"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </Link>

                                            <!-- Activar / Desactivar (solo para roles internos, cuentas de cliente no se pueden desactivar) -->
                                            <button
                                                v-if="user.primary_role !== 'Cliente'"
                                                type="button"
                                                @click="handleToggleStatus(user)"
                                                :disabled="$page.props.auth?.user?.id === user.id"
                                                :title="$page.props.auth?.user?.id === user.id ? 'No puedes desactivar tu propia cuenta' : (user.is_active ? 'Desactivar cuenta' : 'Activar cuenta')"
                                                class="p-1.5 rounded-lg transition disabled:opacity-30 disabled:cursor-not-allowed"
                                                :class="user.is_active ? 'text-gray-400 hover:text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50'"
                                            >
                                                <svg v-if="user.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Estado Vacío (Empty State) -->
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="max-w-xs mx-auto">
                                            <div class="mx-auto h-12 w-12 text-gray-400 flex items-center justify-center rounded-full bg-gray-100 mb-3">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                            <h4 class="text-sm font-semibold text-gray-900">No se encontraron usuarios</h4>
                                            <p class="text-xs text-gray-500 mt-1">
                                                No existen registros que coincidan con los filtros aplicados.
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
                        v-if="users.total > 0"
                        class="bg-white px-4 py-3 sm:px-6 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div class="text-xs text-gray-600">
                            Mostrando del <span class="font-semibold">{{ users.from || 0 }}</span> al <span class="font-semibold">{{ users.to || 0 }}</span> de <span class="font-semibold">{{ users.total }}</span> usuarios
                        </div>

                        <div v-if="users.links && users.links.length > 3" class="flex flex-wrap gap-1 items-center">
                            <template v-for="(link, key) in users.links" :key="key">
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
