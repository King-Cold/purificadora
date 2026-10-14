<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: true,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
});

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
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts.substring(0, 2).toUpperCase();
};

const handleToggleStatus = () => {
    const action = props.user.is_active ? 'desactivar' : 'activar';
    const message = props.user.is_active
        ? `¿Confirmas que deseas desactivar la cuenta de ${props.user.name}? El usuario perderá el acceso inmediato al sistema.`
        : `¿Deseas activar nuevamente la cuenta de ${props.user.name}?`;

    if (confirm(message)) {
        router.patch(route('admin.users.toggle-status', props.user.id), {}, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="`Detalles: ${user.name} - Puritronic`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('admin.users.index')" class="hover:text-blue-900 transition">
                            Usuarios
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">Detalles #{{ user.id }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            {{ user.name }}
                        </h2>
                        <!-- Badge Estado de Cuenta -->
                        <span
                            v-if="user.is_active"
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Cuenta Activa
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Cuenta Inactiva
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Ficha detallada del usuario y credenciales operativas registradas
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
                    <Link
                        :href="route('admin.users.index')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver</span>
                    </Link>

                    <Link
                        v-if="canEdit"
                        :href="route('admin.users.edit', user.id)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-white bg-blue-900 rounded-lg hover:bg-blue-800 transition shadow-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Editar</span>
                    </Link>

                    <!-- Botón Activar / Desactivar -->
                    <button
                        v-if="canEdit"
                        @click="handleToggleStatus"
                        type="button"
                        :disabled="isSelf"
                        :title="isSelf ? 'No puedes desactivar tu propia cuenta' : (user.is_active ? 'Desactivar cuenta de usuario' : 'Activar cuenta de usuario')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg border transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="user.is_active ? 'text-rose-700 bg-white border-rose-300 hover:bg-rose-50' : 'text-emerald-700 bg-white border-emerald-300 hover:bg-emerald-50'"
                    >
                        <svg v-if="user.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ user.is_active ? 'Desactivar' : 'Activar' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Notificaciones Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm"
                >
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-sm"
                >
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.error }}</span>
                </div>

                <!-- Tarjeta Principal: Perfil de Usuario -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Cabecera de Ficha -->
                    <div class="p-6 sm:p-8 bg-gradient-to-r from-blue-900 to-indigo-950 text-white flex flex-col sm:flex-row items-center gap-5">
                        <div class="h-20 w-20 rounded-2xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center font-bold text-2xl tracking-wider text-white shadow-inner shrink-0">
                            {{ getInitials(user.name) }}
                        </div>
                        <div class="text-center sm:text-left flex-1">
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                                <h3 class="text-2xl font-bold tracking-tight">{{ user.name }}</h3>
                                <span class="text-xs px-2 py-0.5 rounded bg-white/20 text-blue-100 font-mono">
                                    #{{ user.id }}
                                </span>
                            </div>
                            <p class="text-sm text-blue-200 font-mono">{{ user.email }}</p>
                            <div class="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                <span
                                    v-for="role in user.roles"
                                    :key="role"
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white text-blue-950 shadow-sm"
                                >
                                    {{ role }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Datos Detallados en Rejilla -->
                    <div class="p-6 sm:p-8">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">
                            Información General del Sistema
                        </h4>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 text-sm">
                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Nombre Completo</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ user.name }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Correo Electrónico</dt>
                                <dd class="mt-1 text-sm font-mono text-gray-900">{{ user.email }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Rol Principal de Acceso</dt>
                                <dd class="mt-1">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs border"
                                        :class="getRoleBadgeClass(user.primary_role)"
                                    >
                                        {{ user.primary_role }}
                                    </span>
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Estado de la Cuenta</dt>
                                <dd class="mt-1">
                                    <span
                                        v-if="user.is_active"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        Activo (Acceso habilitado)
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-700"
                                    >
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        Inactivo (Acceso bloqueado)
                                    </span>
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Verificación de Correo Electrónico</dt>
                                <dd class="mt-1">
                                    <span
                                        v-if="user.is_verified"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700"
                                    >
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Verificado el {{ user.email_verified_at }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700"
                                    >
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Pendiente de verificación
                                    </span>
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Fecha de Registro</dt>
                                <dd class="mt-1 text-sm text-gray-800">{{ user.created_at }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Última Actualización</dt>
                                <dd class="mt-1 text-sm text-gray-800">{{ user.updated_at }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Perfil Comercial Vinculado (Customer) si existe -->
                    <div v-if="user.customer" class="p-6 sm:p-8 border-t border-gray-100 bg-sky-50/30">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="h-7 w-7 rounded bg-sky-100 text-sky-800 flex items-center justify-center font-bold text-xs">
                                    CLI
                                </div>
                                <h4 class="text-sm font-semibold text-gray-900">
                                    Perfil Comercial Asociado (Cliente)
                                </h4>
                            </div>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-mono font-bold bg-sky-100 text-sky-800 border border-sky-200">
                                {{ user.customer.code }}
                            </span>
                        </div>

                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                            <div class="bg-white p-3 rounded-lg border border-sky-100 shadow-2xs">
                                <dt class="text-gray-500 font-medium">Razón Social / Comercial</dt>
                                <dd class="mt-1 font-semibold text-gray-900">
                                    {{ user.customer.business_name || 'Particular' }}
                                </dd>
                            </div>

                            <div class="bg-white p-3 rounded-lg border border-sky-100 shadow-2xs">
                                <dt class="text-gray-500 font-medium">Teléfono Registrado</dt>
                                <dd class="mt-1 font-semibold text-gray-900">
                                    {{ user.customer.phone || 'No registrado' }}
                                </dd>
                            </div>

                            <div class="bg-white p-3 rounded-lg border border-sky-100 shadow-2xs">
                                <dt class="text-gray-500 font-medium">Tarifa / Lista de Precios</dt>
                                <dd class="mt-1 font-semibold text-gray-900 uppercase">
                                    {{ user.customer.price_tier || 'general' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
