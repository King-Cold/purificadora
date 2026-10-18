<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
});

const deleteAddress = (address) => {
    if (confirm('¿Está seguro de eliminar el domicilio de este cliente?')) {
        router.delete(route('admin.customers.addresses.destroy', [props.customer.id, address.id]), {
            preserveScroll: true,
        });
    }
};

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

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Clientes', href: route('admin.customers.index'), icon: 'fa-solid fa-address-book' },
                            { label: customer.code, icon: 'fa-solid fa-id-badge' }
                        ]"
                    />
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="text-xl font-bold leading-tight text-slate-900">
                            {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                        </h2>
                        <!-- Badge Apodo si existe -->
                        <span
                            v-if="customer.nickname"
                            class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-900 border border-blue-200"
                        >
                            "{{ customer.nickname }}"
                        </span>
                        <!-- Badge Código -->
                        <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-800 border border-slate-200">
                            {{ customer.code }}
                        </span>
                        <!-- Badge Estado de Cuenta Único -->
                        <span
                            v-if="customer.has_account"
                            class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                            Con cuenta
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            Sin cuenta
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 self-start sm:self-auto">
                    <Link
                        v-if="canEdit"
                        :href="route('admin.customers.edit', customer.id)"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-blue-900 border border-transparent rounded-xl hover:bg-blue-800 transition shadow-xs"
                    >
                        <i class="fa-solid fa-user-pen text-xs"></i>
                        <span>Editar cliente</span>
                    </Link>

                    <Link
                        :href="route('admin.customers.index')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-xs"
                    >
                        <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                        <span>Volver al directorio</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-[98%] 2xl:max-w-[1850px] px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Mensaje de Éxito Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-xs"
                >
                    <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
                    <span class="text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <!-- Mensaje de Advertencia Flash -->
                <div
                    v-if="$page.props.flash?.warning"
                    class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-center gap-3 shadow-xs"
                >
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 shrink-0"></i>
                    <span class="text-sm font-medium">{{ $page.props.flash.warning }}</span>
                </div>

                <!-- Mensaje de Error Flash -->
                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 flex items-center gap-3 shadow-xs"
                >
                    <i class="fa-solid fa-circle-xmark text-rose-600 shrink-0"></i>
                    <span class="text-sm font-medium">{{ $page.props.flash.error }}</span>
                </div>

                <!-- ============================================================== -->
                <!-- CASO A: FICHA DE CLIENTES CON CUENTA (has_account = true)     -->
                <!-- ============================================================== -->
                <template v-if="customer.has_account">
                    <!-- 1. Datos del Cliente con cuenta -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                        <div class="p-6 bg-slate-900 text-white flex flex-col sm:flex-row items-center gap-5">
                            <div class="h-16 w-16 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center font-bold text-xl text-white shrink-0">
                                {{ getInitials(customer.name) }}
                            </div>
                            <div class="text-center sm:text-left flex-1">
                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5 mb-1">
                                    <h3 class="text-xl font-bold tracking-tight">
                                        {{ customer.name || 'Cliente con cuenta' }}
                                    </h3>
                                    <span v-if="customer.nickname" class="px-2 py-0.5 rounded-lg bg-blue-100 text-blue-900 text-xs font-bold">
                                        "{{ customer.nickname }}"
                                    </span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-lg bg-white/20 text-white font-mono font-bold">
                                        {{ customer.code }}
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center justify-center sm:justify-start gap-4 text-xs text-slate-300">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-sky-400"></i>
                                        {{ customer.phone }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 font-mono">
                                        <i class="fa-solid fa-envelope text-sky-400"></i>
                                        {{ customer.email }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                                Información de Identificación y Contacto
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Nombre completo</dt>
                                    <dd class="mt-1 font-semibold text-slate-900 text-sm">{{ customer.name || 'No registrado' }}</dd>
                                </div>

                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Apodo</dt>
                                    <dd class="mt-1 font-semibold text-blue-900 text-sm">{{ customer.nickname ? `"${customer.nickname}"` : 'Sin apodo registrado' }}</dd>
                                </div>

                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Correo electrónico</dt>
                                    <dd class="mt-1 font-mono font-semibold text-slate-900 text-xs truncate" :title="customer.email">{{ customer.email }}</dd>
                                </div>

                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Teléfono</dt>
                                    <dd class="mt-1 font-semibold text-slate-900 text-sm">{{ customer.phone }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- 2. Domicilio de entrega (Consultar únicamente, NO editar ni eliminar) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4 mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center font-bold text-sm shrink-0 border border-blue-200">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Domicilio de Entrega
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Ubicación de entrega registrada por el cliente en su portal
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold bg-blue-50 text-blue-900 border border-blue-200 self-start sm:self-auto">
                                <i class="fa-solid fa-shield-halved text-blue-700 text-xs"></i>
                                Gestionado exclusivamente por el cliente
                            </span>
                        </div>

                        <!-- Ficha de solo lectura del Domicilio Único -->
                        <div v-if="customer.address" class="p-5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-200">
                                <span class="text-sm font-bold text-slate-900">Dirección de Entrega</span>
                                <span
                                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full border"
                                    :class="customer.address.is_hocaba ? 'bg-blue-100 text-blue-900 border-blue-300' : 'bg-amber-100 text-amber-900 border-amber-300'"
                                >
                                    {{ customer.address.zone_label }}
                                </span>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div>
                                    <dt class="text-slate-500 font-medium">Calle y número</dt>
                                    <dd class="text-slate-900 font-bold text-sm mt-0.5">
                                        {{ customer.address.street_and_number }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-slate-500 font-medium">Municipio</dt>
                                    <dd class="text-slate-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.municipality }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-slate-500 font-medium">Zona de reparto</dt>
                                    <dd class="text-slate-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.zone_label }}
                                    </dd>
                                </div>

                                <div class="sm:col-span-3">
                                    <dt class="text-slate-500 font-medium">Referencias de entrega</dt>
                                    <dd class="text-slate-800 mt-1 bg-white p-3 rounded-lg border border-slate-200 italic">
                                        {{ customer.address.reference || 'Sin referencias registradas.' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Estado vacío si el cliente aún no ha registrado domicilio -->
                        <div v-else class="text-center py-6 bg-slate-50 rounded-xl border border-dashed border-slate-300 text-xs text-slate-500">
                            <i class="fa-solid fa-house-circle-exclamation text-2xl text-slate-400 mb-2"></i>
                            <p class="font-medium text-slate-700">El cliente aún no ha registrado su domicilio de entrega.</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">El cliente podrá configurarlo directamente desde su panel de usuario.</p>
                        </div>
                    </div>

                    <!-- 3. Botellones fijos y de cambio -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center font-bold text-sm shrink-0 border border-blue-200">
                                    <i class="fa-solid fa-bottle-water"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Cantidad de Botellones
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Control de envases en posesión del cliente
                                    </p>
                                </div>
                            </div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-blue-900 text-white text-xs font-bold">
                                <span>Total:</span>
                                <span class="font-mono text-sm">{{ customer.total_bottles }}</span>
                                <span>garrafones</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-slate-900 text-xs">Botellones Fijos</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-blue-600 text-white font-semibold">Rotulados</span>
                                </div>
                                <div class="text-2xl font-bold font-mono text-blue-950 mt-1">
                                    {{ customer.fixed_bottles_count }}
                                    <span class="text-xs font-normal text-slate-600">envase(s)</span>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-sky-50/70 border border-sky-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-slate-900 text-xs">Botellones de Cambio</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-sky-600 text-white font-semibold">Intercambiables</span>
                                </div>
                                <div class="text-2xl font-bold font-mono text-sky-950 mt-1">
                                    {{ customer.exchange_bottles_count }}
                                    <span class="text-xs font-normal text-slate-600">envase(s)</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="customer.notes" class="mt-4 p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                            <span class="font-bold text-slate-700 block mb-1">Notas y observaciones:</span>
                            <p class="text-slate-600 italic">{{ customer.notes }}</p>
                        </div>
                    </div>
                </template>


                <!-- ============================================================== -->
                <!-- CASO B: FICHA DE CLIENTES SIN CUENTA (has_account = false)    -->
                <!-- ============================================================== -->
                <template v-else>
                    <!-- 1. Datos del Cliente sin cuenta (Solo Nombre, Apodo, Código) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                        <div class="p-6 bg-slate-900 text-white flex flex-col sm:flex-row items-center gap-5">
                            <div class="h-16 w-16 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center font-bold text-xl text-white shrink-0">
                                {{ getInitials(customer.name) }}
                            </div>
                            <div class="text-center sm:text-left flex-1">
                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5 mb-1">
                                    <h3 class="text-xl font-bold tracking-tight">
                                        {{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Cliente Particular') }}
                                    </h3>
                                    <span v-if="customer.nickname" class="px-2 py-0.5 rounded-lg bg-blue-100 text-blue-900 text-xs font-bold">
                                        "{{ customer.nickname }}"
                                    </span>
                                    <span class="text-xs px-2.5 py-0.5 rounded-lg bg-white/20 text-white font-mono font-bold">
                                        {{ customer.code }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-300 mt-1">
                                    Cliente sin cuenta registrado en el sistema
                                </p>
                            </div>
                        </div>

                        <div class="p-6">
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                                Identificación del Cliente
                            </h4>
                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Nombre</dt>
                                    <dd class="mt-1 font-semibold text-slate-900 text-sm">{{ customer.name || 'Sin nombre registrado' }}</dd>
                                </div>

                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Apodo</dt>
                                    <dd class="mt-1 font-semibold text-blue-900 text-sm">{{ customer.nickname ? `"${customer.nickname}"` : 'Sin apodo registrado' }}</dd>
                                </div>

                                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                                    <dt class="text-slate-500 font-medium">Código</dt>
                                    <dd class="mt-1 font-mono font-bold text-slate-900 text-sm">{{ customer.code }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- 2. Botellones fijos, botellones de cambio y Observaciones o notas de reparto -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center font-bold text-sm shrink-0 border border-blue-200">
                                    <i class="fa-solid fa-bottle-water"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Botellones y Notas de Reparto
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Cantidades de envases asignados e indicaciones de entrega
                                    </p>
                                </div>
                            </div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-blue-900 text-white text-xs font-bold">
                                <span>Total:</span>
                                <span class="font-mono text-sm">{{ customer.total_bottles }}</span>
                                <span>garrafones</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mb-4">
                            <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-slate-900 text-xs">Cantidad de botellones fijos</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-blue-600 text-white font-semibold">Rotulados</span>
                                </div>
                                <div class="text-2xl font-bold font-mono text-blue-950 mt-1">
                                    {{ customer.fixed_bottles_count }}
                                    <span class="text-xs font-normal text-slate-600">envase(s)</span>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-sky-50/70 border border-sky-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-slate-900 text-xs">Cantidad de botellones de cambio</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-sky-600 text-white font-semibold">Intercambiables</span>
                                </div>
                                <div class="text-2xl font-bold font-mono text-sky-950 mt-1">
                                    {{ customer.exchange_bottles_count }}
                                    <span class="text-xs font-normal text-slate-600">envase(s)</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <span class="font-bold text-slate-700 block mb-1">Observaciones o notas de reparto:</span>
                            <p v-if="customer.notes" class="text-slate-800 leading-relaxed italic">{{ customer.notes }}</p>
                            <p v-else class="text-slate-400 italic">Sin observaciones o notas de reparto registradas.</p>
                        </div>
                    </div>

                    <!-- 3. Domicilio de entrega (Opcional, administrable para clientes sin cuenta) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-4 mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center font-bold text-sm shrink-0 border border-emerald-200">
                                    <i class="fa-solid fa-house"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Domicilio de Entrega (Opcional)
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Puede registrarse posteriormente sin exigirlo de inmediato
                                    </p>
                                </div>
                            </div>

                            <div v-if="!customer.address && canEdit" class="self-start sm:self-auto">
                                <Link
                                    :href="route('admin.customers.addresses.create', customer.id)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-xs"
                                >
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Registrar domicilio</span>
                                </Link>
                            </div>
                        </div>

                        <div v-if="customer.address" class="p-5 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">Casa del Cliente</span>
                                    <span
                                        class="text-xs font-semibold px-2.5 py-0.5 rounded-full border"
                                        :class="customer.address.is_hocaba ? 'bg-blue-100 text-blue-900 border-blue-300' : 'bg-amber-100 text-amber-900 border-amber-300'"
                                    >
                                        {{ customer.address.zone_label }}
                                    </span>
                                </div>

                                <div v-if="canEdit" class="flex items-center gap-2 self-start sm:self-auto">
                                    <Link
                                        :href="route('admin.customers.addresses.edit', [customer.id, customer.address.id])"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg transition shadow-2xs"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-xs text-slate-500"></i>
                                        <span>Editar</span>
                                    </Link>

                                    <button
                                        type="button"
                                        @click="deleteAddress(customer.address)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs text-rose-600"></i>
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </div>

                            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div>
                                    <dt class="text-slate-500 font-medium">Calle y número</dt>
                                    <dd class="text-slate-900 font-bold text-sm mt-0.5">
                                        {{ customer.address.street_and_number }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-slate-500 font-medium">Municipio</dt>
                                    <dd class="text-slate-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.municipality }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-slate-500 font-medium">Zona de reparto</dt>
                                    <dd class="text-slate-900 font-semibold text-sm mt-0.5">
                                        {{ customer.address.zone_label }}
                                    </dd>
                                </div>

                                <div class="sm:col-span-3">
                                    <dt class="text-slate-500 font-medium">Referencias para el repartidor</dt>
                                    <dd class="text-slate-800 mt-1 bg-white p-3 rounded-lg border border-slate-200 italic">
                                        {{ customer.address.reference || 'Sin referencias registradas.' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div v-else class="text-center py-6 bg-slate-50 rounded-xl border border-dashed border-slate-300 text-xs text-slate-500">
                            <i class="fa-solid fa-house-chimney text-2xl text-slate-400 mb-2"></i>
                            <p class="font-medium text-slate-700">Sin domicilio registrado actualmente.</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">El domicilio podrá registrarse posteriormente cuando el cliente lo requiera.</p>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
