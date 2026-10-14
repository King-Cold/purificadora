<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    available_statuses: {
        type: Object,
        default: () => ({
            active: 'Activo',
            inactive: 'Inactivo',
            suspended: 'Suspendido',
        }),
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
});

const isStatusModalOpen = ref(false);

const statusForm = useForm({
    status: props.customer.status || 'active',
    reason: '',
});

const openStatusModal = (targetStatus = null) => {
    statusForm.status = targetStatus || props.customer.status || 'active';
    statusForm.reason = '';
    statusForm.clearErrors();
    isStatusModalOpen.value = true;
};

const closeStatusModal = () => {
    isStatusModalOpen.value = false;
    statusForm.reset();
    statusForm.clearErrors();
};

const submitStatusChange = () => {
    if (statusForm.status === props.customer.status) {
        closeStatusModal();
        return;
    }

    statusForm.patch(route('admin.customers.status.update', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeStatusModal();
        },
    });
};

const deleteAddress = (address) => {
    if (confirm('¿Está seguro de eliminar el domicilio de este cliente?')) {
        router.delete(route('admin.customers.addresses.destroy', [props.customer.id, address.id]), {
            preserveScroll: true,
        });
    }
};

const getStatusBadgeClass = (customerStatus) => {
    switch (customerStatus) {
        case 'active':
            return 'bg-emerald-50 text-emerald-800 border-emerald-300';
        case 'suspended':
            return 'bg-amber-50 text-amber-800 border-amber-300';
        case 'inactive':
            return 'bg-gray-100 text-gray-700 border-gray-300';
        default:
            return 'bg-gray-100 text-gray-700 border-gray-200';
    }
};

const getStatusDotClass = (customerStatus) => {
    switch (customerStatus) {
        case 'active':
            return 'bg-emerald-500';
        case 'suspended':
            return 'bg-amber-500';
        case 'inactive':
            return 'bg-gray-500';
        default:
            return 'bg-gray-400';
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

const getStatusDescription = (customerStatus) => {
    switch (customerStatus) {
        case 'active':
            return 'El cliente está habilitado para recibir atención comercial, solicitar garrafones y recibir entregas a domicilio.';
        case 'inactive':
            return 'El cliente permanece registrado con su historial intacto, pero temporalmente no está habilitado para solicitar nuevos pedidos.';
        case 'suspended':
            return 'El cliente se encuentra suspendido comercialmente para nuevos pedidos. Requiere una decisión administrativa para levantar la suspensión.';
        default:
            return '';
    }
};

const confirmationMessage = computed(() => {
    switch (statusForm.status) {
        case 'suspended':
            return '¿Deseas suspender comercialmente a este cliente? No podrá realizar nuevos pedidos hasta que se reactive.';
        case 'inactive':
            return '¿Deseas desactivar comercialmente a este cliente? Permanecerá registrado con su historial pero temporalmente no podrá realizar nuevos pedidos.';
        case 'active':
            return '¿Deseas activar comercialmente a este cliente? Quedará habilitado para recibir atención comercial y nuevos pedidos.';
        default:
            return '¿Confirmas el cambio de estado comercial para este cliente?';
    }
});

const getInitials = (name) => {
    const text = (name && name.trim()) || (props.customer.nickname && props.customer.nickname.trim()) || 'C';
    const parts = text.split(' ').filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return text.substring(0, 2).toUpperCase();
};
</script>


<template>
    <Head :title="`Cliente: ${customer.name || customer.nickname || 'Particular'} (${customer.code}) - Puritronic`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('admin.customers.index')" class="hover:text-blue-900 transition">
                            Clientes
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">{{ customer.code }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                        </h2>
                        <!-- Badge Apodo si existe -->
                        <span
                            v-if="customer.nickname"
                            class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100/80 text-blue-900 border border-blue-200"
                        >
                            "{{ customer.nickname }}"
                        </span>
                        <!-- Badge Código -->
                        <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-sky-100 text-sky-800 border border-sky-200">
                            {{ customer.code }}
                        </span>
                        <!-- Badge Estado Comercial -->
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
                        <!-- Badge Acceso -->
                        <span
                            v-if="customer.has_account"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-800 border border-blue-200"
                        >
                            <svg class="w-3 h-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Con cuenta en portal
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200"
                        >
                            Sin cuenta en portal
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Ficha completa de cliente particular y domicilios para entrega de garrafones
                    </p>
                </div>

                <div class="flex items-center gap-2.5 self-start sm:self-auto">
                    <Link
                        v-if="canEdit"
                        :href="route('admin.customers.edit', customer.id)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-white bg-blue-900 border border-transparent rounded-lg hover:bg-blue-800 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2"
                    >
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span>Editar cliente</span>
                    </Link>

                    <Link
                        :href="route('admin.customers.index')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Volver al directorio</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Mensaje de Éxito Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm"
                >
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <!-- Mensaje de Advertencia Flash (Duplicados preventivos) -->
                <div
                    v-if="$page.props.flash?.warning"
                    class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center gap-3 shadow-sm"
                >
                    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span class="text-sm font-medium">{{ $page.props.flash.warning }}</span>
                </div>

                <!-- 1. TARJETA PRINCIPAL: DATOS GENERALES DEL CLIENTE PARTICULAR -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Cabecera decorada -->
                    <div class="p-6 sm:p-8 bg-gradient-to-r from-blue-900 to-indigo-950 text-white flex flex-col sm:flex-row items-center gap-5">
                        <div class="h-20 w-20 rounded-2xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center font-bold text-2xl tracking-wider text-white shadow-inner shrink-0">
                            {{ getInitials(customer.name) }}
                        </div>
                        <div class="text-center sm:text-left flex-1">
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                                <h3 class="text-2xl font-bold tracking-tight">
                                    {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                                </h3>
                                <span class="text-xs px-2.5 py-1 rounded-md bg-white/20 text-blue-100 font-mono font-semibold">
                                    {{ customer.code }}
                                </span>
                            </div>
                            <div v-if="customer.name && customer.nickname" class="text-sm text-blue-200 font-medium mb-1">
                                Alias comunitario: <span class="text-white font-bold">"{{ customer.nickname }}"</span>
                            </div>
                            <div v-else-if="!customer.name && customer.nickname" class="text-sm text-blue-200 font-medium mb-1">
                                Identificado en la colonia por su apodo
                            </div>
                            <p class="text-xs text-blue-200/90">
                                Persona física particular registrada para entrega de garrafones de agua
                            </p>
                            <div class="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-2 text-xs text-blue-100">
                                <span class="inline-flex items-center gap-1 bg-white/10 px-2.5 py-1 rounded">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    {{ customer.phone }}
                                </span>
                                <span v-if="customer.email && customer.email !== 'No registrado'" class="inline-flex items-center gap-1 bg-white/10 px-2.5 py-1 rounded font-mono">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ customer.email }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Datos Personales de Contacto -->
                    <div class="p-6 sm:p-8">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">
                            Datos Personales y Contacto
                        </h4>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Nombre Completo</dt>
                                <dd class="mt-1 font-semibold text-gray-900">
                                    {{ customer.name || 'Sin nombre registrado (Identificado por apodo)' }}
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Apodo / Alias Comunitario</dt>
                                <dd class="mt-1 font-semibold text-blue-900">
                                    {{ customer.nickname ? `"${customer.nickname}"` : 'Sin apodo registrado' }}
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Teléfono Principal</dt>
                                <dd class="mt-1 font-semibold text-gray-900">{{ customer.phone }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Teléfono Secundario / WhatsApp</dt>
                                <dd class="mt-1 font-medium text-gray-900">{{ customer.secondary_phone }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Correo Electrónico</dt>
                                <dd class="mt-1 font-mono text-xs text-gray-900 truncate" :title="customer.email">
                                    {{ customer.email }}
                                </dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Fecha de Registro</dt>
                                <dd class="mt-1 text-gray-800">{{ customer.created_at }}</dd>
                            </div>

                            <div class="bg-gray-50/75 p-3.5 rounded-lg border border-gray-100">
                                <dt class="text-xs font-medium text-gray-500">Última Actualización</dt>
                                <dd class="mt-1 text-gray-800">{{ customer.updated_at }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- 2. TARJETA: ESTADO COMERCIAL Y ATENCIÓN AL CLIENTE -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-5 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 border border-blue-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    Estado comercial
                                </h3>
                                <p class="text-xs text-gray-500">
                                    Habilitación comercial para atención de pedidos y entregas (independiente del acceso a la plataforma)
                                </p>
                            </div>
                        </div>

                        <!-- Botón para cambiar estado comercial -->
                        <div v-if="canEdit" class="self-start sm:self-auto">
                            <button
                                type="button"
                                @click="openStatusModal()"
                                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-xs focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Cambiar estado comercial</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cuadrícula informativa: Estado Comercial vs Estado de Acceso -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <!-- Panel Estado Comercial -->
                        <div class="p-4 rounded-xl border bg-gray-50/80 border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">Estado Comercial</span>
                                <span
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border"
                                    :class="getStatusBadgeClass(customer.status)"
                                >
                                    <span class="h-2 w-2 rounded-full" :class="getStatusDotClass(customer.status)"></span>
                                    {{ getStatusLabel(customer.status) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed mb-3">
                                {{ getStatusDescription(customer.status) }}
                            </p>
                            <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between text-xs">
                                <span class="text-gray-500 font-medium">Habilitado para pedidos:</span>
                                <span
                                    class="font-semibold"
                                    :class="customer.status === 'active' ? 'text-emerald-700' : 'text-gray-600'"
                                >
                                    {{ customer.status === 'active' ? 'Sí (Habilitado)' : 'No (Pausado / Suspendido)' }}
                                </span>
                            </div>
                        </div>

                        <!-- Panel Estado de Acceso a Plataforma -->
                        <div class="p-4 rounded-xl border bg-gray-50/80 border-gray-200">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">Estado de Acceso (Usuario)</span>
                                <span
                                    v-if="customer.has_account"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border"
                                    :class="customer.user?.is_active ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-300'"
                                >
                                    <span class="h-2 w-2 rounded-full" :class="customer.user?.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                    {{ customer.user?.is_active ? 'Habilitado' : 'Deshabilitado' }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-300"
                                >
                                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                    Sin cuenta
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed mb-3">
                                <template v-if="customer.has_account">
                                    Cuenta asociada al usuario <strong class="text-gray-800">{{ customer.user?.email }}</strong>. Determina si puede autenticarse en el portal de autoservicio.
                                </template>
                                <template v-else>
                                    Este cliente particular no posee cuenta web de acceso. Sus pedidos y atención se gestionan directamente por el personal de la purificadora.
                                </template>
                            </p>
                            <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between text-xs">
                                <span class="text-gray-500 font-medium">Autenticación en portal:</span>
                                <span class="font-semibold text-gray-800">
                                    {{ customer.has_account ? (customer.user?.is_active ? 'Permitida' : 'Bloqueada por módulo de usuarios') : 'No aplica (Sin credenciales)' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sección Historial de Cambios de Estado Comercial -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Historial de Cambios Comerciales
                            </h4>
                            <span class="text-xs text-gray-400 font-medium">
                                {{ customer.status_histories?.length || 0 }} registro(s)
                            </span>
                        </div>

                        <!-- Tabla de solo lectura del historial -->
                        <div v-if="customer.status_histories && customer.status_histories.length > 0" class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                                <thead class="bg-gray-50 text-gray-600 font-semibold uppercase tracking-wider">
                                    <tr>
                                        <th scope="col" class="px-4 py-2.5">Fecha y hora</th>
                                        <th scope="col" class="px-4 py-2.5">Estado anterior</th>
                                        <th scope="col" class="px-4 py-2.5">Estado nuevo</th>
                                        <th scope="col" class="px-4 py-2.5">Motivo / Justificación</th>
                                        <th scope="col" class="px-4 py-2.5">Responsable</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="item in customer.status_histories" :key="item.id" class="hover:bg-gray-50/50">
                                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-700 font-medium">
                                            {{ item.created_at }}
                                        </td>
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold border"
                                                :class="getStatusBadgeClass(item.previous_status)"
                                            >
                                                {{ item.previous_status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold border"
                                                :class="getStatusBadgeClass(item.new_status)"
                                            >
                                                {{ item.new_status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-700 max-w-xs">
                                            <span v-if="item.reason" class="italic">"{{ item.reason }}"</span>
                                            <span v-else class="text-gray-400">Sin motivo registrado</span>
                                        </td>
                                        <td class="px-4 py-2.5 whitespace-nowrap text-gray-700 font-medium">
                                            {{ item.changed_by_name }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Estado vacío si no hay historial aún -->
                        <div v-else class="text-center py-5 px-4 bg-gray-50/60 rounded-lg border border-dashed border-gray-200">
                            <p class="text-xs text-gray-500">
                                No se registran cambios de estado comercial previos. El cliente conserva su estado de registro original.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 3. TARJETA: ACCESO AL SISTEMA / CUENTA VINCULADA -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-5">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Acceso al Portal de Autoservicio
                                </h3>
                                <p class="text-xs text-gray-500">
                                    Estado de credenciales de usuario vinculadas para pedidos en línea
                                </p>
                            </div>
                        </div>

                        <span
                            v-if="customer.has_account"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
                        >
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Cuenta Vinculada Activa
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200"
                        >
                            <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                            Sin cuenta vinculada
                        </span>
                    </div>

                    <!-- Si el cliente TIENE cuenta vinculada -->
                    <div v-if="customer.has_account && customer.user" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                        <div class="bg-gray-50 p-3.5 rounded-lg border border-gray-200/80">
                            <span class="text-gray-500 font-medium block">Correo de Inicio de Sesión</span>
                            <span class="font-mono text-gray-900 font-semibold mt-1 block truncate" :title="customer.user.email">
                                {{ customer.user.email }}
                            </span>
                        </div>

                        <div class="bg-gray-50 p-3.5 rounded-lg border border-gray-200/80">
                            <span class="text-gray-500 font-medium block">Estado de la Cuenta de Acceso</span>
                            <span class="inline-flex items-center gap-1.5 font-semibold mt-1" :class="customer.user.is_active ? 'text-emerald-700' : 'text-rose-700'">
                                <span class="h-1.5 w-1.5 rounded-full" :class="customer.user.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                {{ customer.user.is_active ? 'Activa (Permite login)' : 'Inactiva (Bloqueada)' }}
                            </span>
                        </div>

                        <div class="bg-gray-50 p-3.5 rounded-lg border border-gray-200/80">
                            <span class="text-gray-500 font-medium block">Verificación de Correo</span>
                            <span class="inline-flex items-center gap-1 font-medium mt-1" :class="customer.user.is_verified ? 'text-emerald-700' : 'text-amber-700'">
                                <svg v-if="customer.user.is_verified" class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ customer.user.is_verified ? customer.user.email_verified_at : 'Pendiente' }}
                            </span>
                        </div>

                        <div class="bg-gray-50 p-3.5 rounded-lg border border-gray-200/80 flex items-center justify-between">
                            <div>
                                <span class="text-gray-500 font-medium block">Identificador de Usuario</span>
                                <span class="font-mono text-gray-900 font-semibold mt-1 block">#{{ customer.user.id }}</span>
                            </div>
                            <Link
                                :href="route('admin.users.show', customer.user.id)"
                                class="text-blue-900 hover:text-blue-700 text-xs font-semibold inline-flex items-center gap-1 bg-blue-100/60 hover:bg-blue-100 px-2 py-1 rounded transition"
                                title="Ver ficha del usuario"
                            >
                                <span>Ver usuario</span>
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- Si el cliente NO TIENE cuenta vinculada -->
                    <div v-else class="bg-gray-50 border border-gray-200/80 rounded-lg p-4 flex items-start gap-3">
                        <div class="h-8 w-8 rounded-full bg-gray-200/80 flex items-center justify-center shrink-0 text-gray-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="text-xs text-gray-600">
                            <h5 class="font-semibold text-gray-900">Cliente sin cuenta web de autoservicio</h5>
                            <p class="mt-0.5 leading-relaxed">
                                Este cliente particular no cuenta con usuario para iniciar sesión en el portal. Sus solicitudes y pedidos son administrados de manera manual por llamada telefónica o en visita directa de reparto.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 3. TARJETA: DOMICILIO DE ENTREGA (CASA) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 pb-4 mb-5">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Domicilio de Entrega (Casa)
                                </h3>
                                <p class="text-xs text-gray-500">
                                    Ubicación de entrega habitual para el reparto de garrafones
                                </p>
                            </div>
                        </div>

                        <!-- Si no tiene domicilio, permitir registrarlo -->
                        <div v-if="!customer.address" class="self-start sm:self-auto">
                            <Link
                                :href="route('admin.customers.addresses.create', customer.id)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Registrar domicilio</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Ficha del Domicilio Único -->
                    <div v-if="customer.address" class="space-y-4">
                        <div class="p-5 rounded-xl border border-gray-200 bg-gray-50/50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3 mb-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900">
                                        Casa del Cliente
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border"
                                        :class="customer.address.is_hocaba ? 'bg-blue-50 text-blue-900 border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                    >
                                        {{ customer.address.zone_label }}
                                    </span>
                                </div>

                                <!-- Acciones del Domicilio Único -->
                                <div class="flex items-center gap-2 self-start sm:self-auto">
                                    <Link
                                        :href="route('admin.customers.addresses.edit', [customer.id, customer.address.id])"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg transition shadow-2xs"
                                        title="Editar domicilio"
                                    >
                                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span>Editar domicilio</span>
                                    </Link>

                                    <button
                                        type="button"
                                        @click="deleteAddress(customer.address)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition"
                                        title="Eliminar este domicilio"
                                    >
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div>
                                    <dt class="text-gray-500 font-medium">Calle y Número</dt>
                                    <dd class="text-gray-900 font-bold text-sm mt-0.5">
                                        {{ customer.address.street_and_number }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-gray-500 font-medium">Municipio</dt>
                                    <dd class="text-gray-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.municipality }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-gray-500 font-medium">Zona de Reparto</dt>
                                    <dd class="text-gray-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.zone_label }}
                                    </dd>
                                </div>

                                <div class="sm:col-span-3">
                                    <dt class="text-gray-500 font-medium">Referencias para el Repartidor</dt>
                                    <dd class="text-gray-800 italic mt-1 bg-white p-3 rounded-lg border border-gray-100">
                                        {{ customer.address.reference || 'Sin referencias registradas.' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Estado Vacío: Si no tiene domicilio -->
                    <div v-else class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        <h4 class="mt-2 text-sm font-semibold text-gray-900">Este cliente todavía no tiene una dirección registrada</h4>
                        <p class="mt-1 text-xs text-gray-500 max-w-sm mx-auto">
                            Registre la ubicación de su casa para la entrega de garrafones de agua.
                        </p>
                        <div class="mt-4">
                            <Link
                                :href="route('admin.customers.addresses.create', customer.id)"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Registrar domicilio</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 4. TARJETA: BOTELLONES REGISTRADOS -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 pb-4 mb-5">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Botellones registrados
                                </h3>
                                <p class="text-xs text-gray-500">
                                    Control de envases en posesión del cliente (fijos identificados y de cambio)
                                </p>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold self-start sm:self-auto shadow-2xs">
                            <span class="text-gray-600">Total en posesión:</span>
                            <span class="text-base font-bold text-blue-950 font-mono">{{ customer.total_bottles }}</span>
                            <span class="text-gray-600">garrafones</span>
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <!-- Botellones Fijos -->
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <dt class="text-xs font-bold text-gray-900">Botellones Fijos</dt>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-semibold">Rotulados con su nombre</span>
                                </div>
                                <dd class="mt-2">
                                    <span class="text-2xl font-extrabold text-blue-950 font-mono">{{ customer.fixed_bottles_count }}</span>
                                    <span class="text-xs text-gray-500 ml-1.5 font-medium">envase(s)</span>
                                </dd>
                            </div>
                            <p class="text-xs text-gray-600 mt-3 leading-relaxed">
                                Pertenecen al cliente. Identificados con su nombre. Se recogen para su lavado y llenado en planta y se devuelve exactamente el mismo envase.
                            </p>
                        </div>

                        <!-- Botellones de Cambio -->
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <dt class="text-xs font-bold text-gray-900">Botellones de Cambio</dt>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-sky-100 text-sky-900 font-semibold">Intercambiables</span>
                                </div>
                                <dd class="mt-2">
                                    <span class="text-2xl font-extrabold text-sky-950 font-mono">{{ customer.exchange_bottles_count }}</span>
                                    <span class="text-xs text-gray-500 ml-1.5 font-medium">envase(s)</span>
                                </dd>
                            </div>
                            <p class="text-xs text-gray-600 mt-3 leading-relaxed">
                                Envases universales sin identificación personal. Se entregan vacíos y se intercambian de inmediato por otro botellón lleno durante la visita del repartidor.
                            </p>
                        </div>

                        <!-- Modalidad de Envases -->
                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200/80 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <dt class="text-xs font-bold text-gray-900">Modalidad de Envases</dt>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-semibold">Activa</span>
                                </div>
                                <dd class="mt-2 text-sm font-semibold text-gray-900">
                                    <span v-if="customer.fixed_bottles_count > 0 && customer.exchange_bottles_count > 0" class="text-emerald-700">
                                        Mixto (Fijos y de Cambio)
                                    </span>
                                    <span v-else-if="customer.fixed_bottles_count > 0" class="text-blue-900">
                                        Exclusivo Fijos
                                    </span>
                                    <span v-else-if="customer.exchange_bottles_count > 0" class="text-sky-900">
                                        Exclusivo Cambio
                                    </span>
                                    <span v-else class="text-gray-500 font-normal">
                                        Sin botellones registrados aún
                                    </span>
                                </dd>
                            </div>
                            <p class="text-xs text-gray-600 mt-3 leading-relaxed">
                                El cliente puede tener ambos tipos de botellones simultáneamente según sus necesidades de servicio.
                            </p>
                        </div>

                        <div v-if="customer.notes" class="sm:col-span-3 bg-gray-50 p-4 rounded-lg border border-gray-200/80 mt-2">
                            <dt class="text-gray-500 font-medium">Notas y Observaciones de Entrega</dt>
                            <dd class="mt-1 text-gray-800 leading-relaxed italic">
                                {{ customer.notes }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>

        <!-- MODAL: CAMBIO DE ESTADO COMERCIAL -->
        <div
            v-if="isStatusModalOpen"
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
        >
            <!-- Fondo oscuro con desenfoque -->
            <div
                class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
                @click="closeStatusModal"
            ></div>

            <!-- Contenedor del Modal -->
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-200 z-10 animate-fade-in space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">
                                Cambiar Estado Comercial
                            </h3>
                            <p class="text-xs text-gray-500">
                                Cliente: {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Particular') }} ({{ customer.code }})
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeStatusModal"
                        class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitStatusChange" class="space-y-4">
                    <!-- Selector de nuevo estado -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                            Seleccionar Nuevo Estado Comercial
                        </label>
                        <div class="grid grid-cols-3 gap-2.5">
                            <!-- Opción Activo -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center cursor-pointer transition select-none"
                                :class="statusForm.status === 'active'
                                    ? 'bg-emerald-50 border-emerald-500 ring-2 ring-emerald-500/20 text-emerald-950 font-bold'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                            >
                                <input
                                    type="radio"
                                    name="commercial_status"
                                    value="active"
                                    v-model="statusForm.status"
                                    class="sr-only"
                                />
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 mb-1"></span>
                                <span class="text-xs">Activo</span>
                            </label>

                            <!-- Opción Inactivo -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center cursor-pointer transition select-none"
                                :class="statusForm.status === 'inactive'
                                    ? 'bg-gray-100 border-gray-500 ring-2 ring-gray-400/20 text-gray-900 font-bold'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                            >
                                <input
                                    type="radio"
                                    name="commercial_status"
                                    value="inactive"
                                    v-model="statusForm.status"
                                    class="sr-only"
                                />
                                <span class="h-2.5 w-2.5 rounded-full bg-gray-500 mb-1"></span>
                                <span class="text-xs">Inactivo</span>
                            </label>

                            <!-- Opción Suspendido -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-xl border text-center cursor-pointer transition select-none"
                                :class="statusForm.status === 'suspended'
                                    ? 'bg-amber-50 border-amber-500 ring-2 ring-amber-500/20 text-amber-950 font-bold'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                            >
                                <input
                                    type="radio"
                                    name="commercial_status"
                                    value="suspended"
                                    v-model="statusForm.status"
                                    class="sr-only"
                                />
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-500 mb-1"></span>
                                <span class="text-xs">Suspendido</span>
                            </label>
                        </div>
                        <p v-if="statusForm.errors.status" class="mt-1 text-xs text-rose-600">
                            {{ statusForm.errors.status }}
                        </p>
                    </div>

                    <!-- Mensaje de confirmación explícito según el estado -->
                    <div
                        class="p-3.5 rounded-xl border text-xs leading-relaxed"
                        :class="{
                            'bg-amber-50 border-amber-200 text-amber-900': statusForm.status === 'suspended',
                            'bg-gray-50 border-gray-300 text-gray-800': statusForm.status === 'inactive',
                            'bg-emerald-50 border-emerald-200 text-emerald-900': statusForm.status === 'active',
                        }"
                    >
                        <div class="font-semibold mb-0.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Confirmación:
                        </div>
                        <p>{{ confirmationMessage }}</p>
                    </div>

                    <!-- Motivo opcional -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="status_reason" class="block text-xs font-medium text-gray-700">
                                Motivo o justificación del cambio <span class="text-gray-400 font-normal">(Opcional)</span>
                            </label>
                            <span class="text-[11px] text-gray-400 font-mono">
                                {{ statusForm.reason ? statusForm.reason.length : 0 }}/500
                            </span>
                        </div>
                        <textarea
                            id="status_reason"
                            v-model="statusForm.reason"
                            maxlength="500"
                            rows="3"
                            placeholder="Ej. Solicitud voluntaria del cliente, periodo vacacional, acuerdo administrativo..."
                            class="block w-full text-xs rounded-lg border-gray-300 shadow-2xs focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                        <p v-if="statusForm.errors.reason" class="mt-1 text-xs text-rose-600">
                            {{ statusForm.errors.reason }}
                        </p>
                    </div>

                    <!-- Nota de seguridad y separación de entidades -->
                    <div class="p-3 rounded-lg bg-blue-50/70 border border-blue-200/60 text-[11px] text-blue-900 flex items-start gap-2">
                        <svg class="w-4 h-4 text-blue-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <p class="leading-relaxed">
                            <strong>Independencia de estados:</strong> Esta acción no elimina al cliente ni modifica el estado de acceso de su cuenta de usuario en el portal si cuenta con una.
                        </p>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="closeStatusModal"
                            class="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="statusForm.processing || statusForm.status === customer.status"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-xs focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <svg v-if="statusForm.processing" class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ statusForm.status === customer.status ? 'Estado actual seleccionado' : 'Confirmar cambio' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
