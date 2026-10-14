<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

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
    <Head :title="`Cliente: ${customer.name} (${customer.code}) - Puritronic`" />

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
                            {{ customer.name }}
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
                                <h3 class="text-2xl font-bold tracking-tight">{{ customer.name }}</h3>
                                <span class="text-xs px-2.5 py-1 rounded-md bg-white/20 text-blue-100 font-mono font-semibold">
                                    {{ customer.code }}
                                </span>
                            </div>
                            <div v-if="customer.nickname" class="text-sm text-blue-200 font-medium mb-1">
                                Alias comunitario: <span class="text-white font-bold">"{{ customer.nickname }}"</span>
                            </div>
                            <p class="text-xs text-blue-200/90">
                                Persona física particular registrada para entrega de agua purificada
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
                                <dd class="mt-1 font-semibold text-gray-900">{{ customer.name }}</dd>
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

                <!-- 2. TARJETA: ACCESO AL SISTEMA / CUENTA VINCULADA -->
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

                <!-- 3. TARJETA: DOMICILIOS DE ENTREGA -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-5">
                        <div class="flex items-center gap-2.5">
                            <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Domicilios de Entrega
                                </h3>
                                <p class="text-xs text-gray-500">
                                    Ubicaciones registradas para el reparto y recolección de garrafones
                                </p>
                            </div>
                        </div>

                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                            {{ customer.addresses_count }} registrado(s)
                        </span>
                    </div>

                    <!-- Listado de Domicilios -->
                    <div v-if="customer.addresses && customer.addresses.length > 0" class="space-y-4">
                        <div
                            v-for="address in customer.addresses"
                            :key="address.id"
                            class="p-4 sm:p-5 rounded-xl border transition"
                            :class="address.is_default ? 'bg-emerald-50/30 border-emerald-300 ring-1 ring-emerald-200 shadow-2xs' : 'bg-gray-50/50 border-gray-200'"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3 mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900">
                                        {{ address.label }}
                                    </span>
                                    <span
                                        v-if="address.is_default"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-100/70 border border-emerald-200 px-2 py-0.5 rounded-full"
                                    >
                                        <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        Domicilio Principal Predeterminado
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400 font-mono">
                                    ID: #{{ address.id }}
                                </span>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <dt class="text-gray-500 font-medium">Dirección Completa</dt>
                                    <dd class="text-gray-900 font-semibold mt-0.5">
                                        {{ address.street }} #{{ address.exterior_number }}
                                        <span v-if="address.interior_number">Int. {{ address.interior_number }}</span>
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-gray-500 font-medium">Colonia y Código Postal</dt>
                                    <dd class="text-gray-900 font-medium mt-0.5">
                                        Col. {{ address.neighborhood || 'N/D' }} - C.P. {{ address.postal_code || 'N/D' }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-gray-500 font-medium">Ciudad y Estado</dt>
                                    <dd class="text-gray-900 font-medium mt-0.5">
                                        {{ address.city || 'N/D' }}, {{ address.state || 'N/D' }}
                                    </dd>
                                </div>

                                <div v-if="address.between_streets">
                                    <dt class="text-gray-500 font-medium">Entre Calles</dt>
                                    <dd class="text-gray-800 mt-0.5">{{ address.between_streets }}</dd>
                                </div>

                                <div v-if="address.reference" class="sm:col-span-2">
                                    <dt class="text-gray-500 font-medium">Referencias para el Repartidor</dt>
                                    <dd class="text-gray-800 italic mt-0.5 bg-white p-2 rounded border border-gray-100">
                                        "{{ address.reference }}"
                                    </dd>
                                </div>

                                <div v-if="address.delivery_zone">
                                    <dt class="text-gray-500 font-medium">Zona / Sector de Reparto</dt>
                                    <dd class="text-gray-800 font-semibold mt-0.5">{{ address.delivery_zone }}</dd>
                                </div>

                                <div v-if="address.preferred_time_window">
                                    <dt class="text-gray-500 font-medium">Horario Preferente de Entrega</dt>
                                    <dd class="text-gray-800 font-medium mt-0.5">{{ address.preferred_time_window }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Estado Vacío de Domicilios -->
                    <div v-else class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-300">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        <h4 class="mt-2 text-sm font-semibold text-gray-900">Sin domicilios registrados</h4>
                        <p class="mt-1 text-xs text-gray-500">
                            Este cliente aún no tiene direcciones dadas de alta para el servicio de reparto.
                        </p>
                    </div>
                </div>

                <!-- 4. TARJETA: CONDICIONES COMERCIALES BÁSICAS -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sm:p-8">
                    <div class="flex items-center gap-2.5 border-b border-gray-100 pb-4 mb-5">
                        <div class="h-8 w-8 rounded-lg bg-indigo-50 text-indigo-800 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">
                                Información Comercial Básica
                            </h3>
                            <p class="text-xs text-gray-500">
                                Tabulador de precio y condiciones de entrega registradas en el sistema
                            </p>
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200/80">
                            <dt class="text-gray-500 font-medium">Lista / Tarifa de Precios</dt>
                            <dd class="mt-1.5 flex items-center gap-1.5">
                                <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-900 uppercase">
                                    {{ customer.price_tier }}
                                </span>
                            </dd>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200/80">
                            <dt class="text-gray-500 font-medium">Condiciones de Crédito</dt>
                            <dd class="mt-1.5">
                                <span v-if="customer.has_credit" class="font-semibold text-gray-900">
                                    Límite: ${{ customer.credit_limit.toFixed(2) }} ({{ customer.credit_days }} días)
                                </span>
                                <span v-else class="text-gray-600 font-medium">
                                    De contado (Sin crédito autorizado)
                                </span>
                            </dd>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200/80">
                            <dt class="text-gray-500 font-medium">Comodato / Préstamo de Envases</dt>
                            <dd class="mt-1.5">
                                <span v-if="customer.has_deposit_bottles" class="text-emerald-700 font-semibold inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Autorizado para préstamo
                                </span>
                                <span v-else class="text-gray-600 font-medium">
                                    A cambio de envase vacío
                                </span>
                            </dd>
                        </div>

                        <div v-if="customer.notes" class="sm:col-span-3 bg-gray-50 p-4 rounded-lg border border-gray-200/80">
                            <dt class="text-gray-500 font-medium">Notas Administrativas Internas</dt>
                            <dd class="mt-1 text-gray-800 leading-relaxed italic">
                                {{ customer.notes }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
