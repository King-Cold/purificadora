<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/AdminPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { confirmAction } from '@/utils/swal';

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
            return 'bg-[#F4F3F8] text-[#1E1545] border-[#DAD6E8] font-semibold';
        case 'Administrador':
            return 'bg-[#E9F3F8] text-[#0071BA] border-[#DCECF7] font-semibold';
        case 'Repartidor':
            return 'bg-[#F8F0DF] text-[#A97827] border-[#A97827]/25 font-semibold';
        case 'Operador Planta':
            return 'bg-[#E8F2EC] text-[#34785A] border-[#34785A]/25 font-semibold';
        case 'Cliente':
            return 'bg-[#DCECF7]/60 text-[#0071BA] border-[#DCECF7] font-medium';
        default:
            return 'bg-[#F4F3F8] text-[#747181] border-[#E9E7F0] font-medium';
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

const handleToggleStatus = async (user) => {
    const isCurrentActive = user.is_active;
    const confirmed = await confirmAction({
        title: isCurrentActive ? '¿Desactivar cuenta?' : '¿Activar cuenta?',
        text: isCurrentActive
            ? `¿Confirmas que deseas desactivar la cuenta de ${user.name}? Se revocará su acceso al sistema inmediatamente.`
            : `¿Deseas activar nuevamente la cuenta de ${user.name}?`,
        confirmText: isCurrentActive ? 'Sí, desactivar' : 'Sí, activar',
        icon: 'warning',
        danger: isCurrentActive,
    });

    if (confirmed) {
        router.patch(route('admin.users.toggle-status', user.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <AdminPageHeader
                title="Gestión de Usuarios"
                subtitle="Cuentas internas del personal, roles operativos y control de acceso."
                icon="fa-solid fa-users"
                accent="purple"
                :badge-text="`${totalUsersCount} registrados`"
                :breadcrumbs="[
                    { label: 'Administración', href: route('dashboard') },
                    { label: 'Usuarios' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="route('admin.users.create')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#1E1545] hover:bg-[#2B1F60] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-[0_1px_2px_rgba(30,21,69,0.12)] transition duration-150 active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]/30"
                    >
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Nuevo Usuario</span>
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
                                placeholder="Buscar por nombre o correo electrónico..."
                                class="block w-full pl-10 pr-4 py-2 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] placeholder-[#A6A3B2] transition"
                            />
                        </div>

                        <!-- Filtro por Rol -->
                        <div class="md:col-span-3">
                            <select
                                v-model="role"
                                class="block w-full py-2 px-3 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] font-medium transition"
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
                                class="block w-full py-2 px-3 text-sm bg-[#F4F3F8]/60 focus:bg-white border border-[#E9E7F0] rounded-xl focus:ring-2 focus:ring-[#0071BA]/15 focus:border-[#0071BA] text-[#292638] font-medium transition"
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
                            <span>Mostrando <strong class="text-[#292638] tabular-nums">{{ users.total }}</strong> usuarios encontrados</span>
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

                <!-- Contenedor de Usuarios: Tarjetas en móvil y Tabla en escritorio -->
                <div class="bg-white rounded-2xl shadow-sm border border-[#E9E7F0] overflow-hidden">

                    <!-- VISTA MÓVIL (PANTALLAS < MD): TARJETAS COMPACTAS -->
                    <div class="md:hidden divide-y divide-[#E9E7F0]">
                        <div
                            v-for="user in users.data"
                            :key="user.id"
                            class="p-4 space-y-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="h-10 w-10 rounded-xl bg-[#1E1545] text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-sm text-[#292638] leading-tight truncate">
                                            {{ user.name }}
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-xs text-[#747181]">
                                            <span class="tabular-nums text-[11px] text-[#A6A3B2] font-medium">#{{ user.id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] border shrink-0"
                                    :class="getRoleBadgeClass(user.primary_role)"
                                >
                                    {{ user.primary_role }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-[#747181] bg-[#F4F3F8]/70 px-3 py-2.5 rounded-xl border border-[#E9E7F0]">
                                <span class="text-xs text-[#292638] truncate max-w-[190px]">{{ user.email }}</span>
                                <span
                                    v-if="user.is_active"
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#34785A] bg-[#E8F2EC] px-2.5 py-0.5 rounded-full border border-[#34785A]/20 shrink-0"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#34785A]"></span>
                                    Activo
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-[#E82147] bg-[#FCECEF] px-2.5 py-0.5 rounded-full border border-[#E82147]/20 shrink-0"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-[#E82147]"></span>
                                    Inactivo
                                </span>
                            </div>

                            <!-- Acciones táctiles para móvil -->
                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]/70">
                                <Link
                                    :href="route('admin.users.show', user.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#0071BA] bg-[#E9F3F8] hover:bg-[#DCECF7] rounded-xl transition"
                                >
                                    <i class="fa-solid fa-eye text-[11px]"></i>
                                    <span>Ver</span>
                                </Link>

                                <Link
                                    :href="route('admin.users.edit', user.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#292638] bg-[#F4F3F8] hover:bg-[#E9E7F0] rounded-xl transition"
                                >
                                    <i class="fa-solid fa-pen text-[11px]"></i>
                                    <span>Editar</span>
                                </Link>

                                <button
                                    v-if="user.primary_role !== 'Cliente'"
                                    type="button"
                                    @click="handleToggleStatus(user)"
                                    :disabled="$page.props.auth?.user?.id === user.id"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition disabled:opacity-35 disabled:cursor-not-allowed"
                                    :class="user.is_active ? 'text-[#E82147] bg-[#FCECEF] hover:bg-[#FCECEF]/80' : 'text-[#34785A] bg-[#E8F2EC] hover:bg-[#E8F2EC]/80'"
                                >
                                    <i :class="user.is_active ? 'fa-solid fa-ban' : 'fa-solid fa-check'" class="text-[11px]"></i>
                                    <span>{{ user.is_active ? 'Desactivar' : 'Activar' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Estado vacío móvil -->
                        <div v-if="users.data.length === 0" class="p-10 text-center">
                            <div class="mx-auto h-12 w-12 text-[#747181] flex items-center justify-center rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] mb-3">
                                <i class="fa-solid fa-user-slash text-lg"></i>
                            </div>
                            <h4 class="text-sm font-semibold text-[#292638]">No se encontraron usuarios</h4>
                            <p class="text-xs text-[#747181] mt-1">
                                No existen registros que coincidan con los filtros aplicados.
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
                    </div>

                    <!-- VISTA ESCRITORIO (PANTALLAS >= MD): TABLA -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#E9E7F0] text-left">
                            <thead class="bg-[#F4F3F8] text-[11px] uppercase tracking-wider text-[#747181] font-semibold">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-6 pr-3">Usuario</th>
                                    <th scope="col" class="px-3 py-3.5">Correo Electrónico</th>
                                    <th scope="col" class="px-3 py-3.5">Rol de Acceso</th>
                                    <th scope="col" class="px-3 py-3.5">Estado</th>
                                    <th scope="col" class="px-3 py-3.5">Registro</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E9E7F0] bg-white text-sm">
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="hover:bg-[#F4F3F8]/60 transition-colors duration-150"
                                >
                                    <!-- Nombre y Avatar -->
                                    <td class="whitespace-nowrap py-3.5 pl-6 pr-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-[#1E1545] text-white flex items-center justify-center font-bold text-xs tracking-wide shrink-0">
                                                {{ getInitials(user.name) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-[#292638] leading-tight">
                                                    {{ user.name }}
                                                </div>
                                                <div class="text-xs text-[#A6A3B2] mt-0.5 flex items-center gap-1.5 tabular-nums">
                                                    <span>ID #{{ user.id }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Correo -->
                                    <td class="whitespace-nowrap px-3 py-3.5 text-[#747181] text-xs font-medium">
                                        {{ user.email }}
                                    </td>

                                    <!-- Rol -->
                                    <td class="whitespace-nowrap px-3 py-3.5">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs border"
                                            :class="getRoleBadgeClass(user.primary_role)"
                                        >
                                            {{ user.primary_role }}
                                        </span>
                                    </td>

                                    <!-- Estado de Cuenta y Verificación -->
                                    <td class="whitespace-nowrap px-3 py-3.5">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                v-if="user.is_active"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#E8F2EC] text-[#34785A] border border-[#34785A]/20 w-fit"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#34785A]"></span>
                                                Activo
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#FCECEF] text-[#E82147] border border-[#E82147]/20 w-fit"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#E82147]"></span>
                                                Inactivo
                                            </span>
                                            <span class="text-[11px] text-[#A6A3B2]">
                                                {{ user.is_verified ? 'Email verificado' : 'Email pendiente' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Fecha de Registro -->
                                    <td class="whitespace-nowrap px-3 py-3.5 text-xs text-[#747181] tabular-nums">
                                        {{ user.created_at }}
                                    </td>

                                    <!-- Columna Acciones -->
                                    <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right text-xs">
                                        <div class="inline-flex items-center justify-end gap-1">
                                            <!-- Ver detalles -->
                                            <Link
                                                :href="route('admin.users.show', user.id)"
                                                class="h-8 w-8 inline-flex items-center justify-center text-[#747181] hover:text-[#0071BA] hover:bg-[#E9F3F8] rounded-lg transition"
                                                title="Ver detalles"
                                                aria-label="Ver detalles"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </Link>

                                            <!-- Editar -->
                                            <Link
                                                :href="route('admin.users.edit', user.id)"
                                                class="h-8 w-8 inline-flex items-center justify-center text-[#747181] hover:text-[#1E1545] hover:bg-[#F4F3F8] rounded-lg transition"
                                                title="Editar usuario"
                                                aria-label="Editar usuario"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </Link>

                                            <!-- Activar / Desactivar (solo para roles internos) -->
                                            <button
                                                v-if="user.primary_role !== 'Cliente'"
                                                type="button"
                                                @click="handleToggleStatus(user)"
                                                :disabled="$page.props.auth?.user?.id === user.id"
                                                :title="$page.props.auth?.user?.id === user.id ? 'No puedes desactivar tu propia cuenta' : (user.is_active ? 'Desactivar cuenta' : 'Activar cuenta')"
                                                :aria-label="user.is_active ? 'Desactivar cuenta' : 'Activar cuenta'"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg transition disabled:opacity-30 disabled:cursor-not-allowed"
                                                :class="user.is_active ? 'text-[#747181] hover:text-[#E82147] hover:bg-[#FCECEF]' : 'text-[#34785A] hover:bg-[#E8F2EC]'"
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

                                <!-- Estado Vacío Escritorio -->
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="px-6 py-14 text-center">
                                        <div class="max-w-xs mx-auto">
                                            <div class="mx-auto h-12 w-12 text-[#747181] flex items-center justify-center rounded-2xl bg-[#F4F3F8] border border-[#E9E7F0] mb-3">
                                                <i class="fa-solid fa-user-slash text-lg"></i>
                                            </div>
                                            <h4 class="text-sm font-semibold text-[#292638]">No se encontraron usuarios</h4>
                                            <p class="text-xs text-[#747181] mt-1">
                                                No existen registros que coincidan con los filtros aplicados.
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
                        v-if="users.total > 0"
                        class="bg-[#F4F3F8]/40 px-4 py-3.5 sm:px-6 border-t border-[#E9E7F0] flex flex-col sm:flex-row items-center justify-between gap-4"
                    >
                        <div class="text-xs text-[#747181] tabular-nums">
                            Mostrando del <span class="font-semibold text-[#292638]">{{ users.from || 0 }}</span> al <span class="font-semibold text-[#292638]">{{ users.to || 0 }}</span> de <span class="font-semibold text-[#292638]">{{ users.total }}</span> usuarios
                        </div>

                        <div v-if="users.links && users.links.length > 3" class="flex flex-wrap gap-1.5 items-center">
                            <template v-for="(link, key) in users.links" :key="key">
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
