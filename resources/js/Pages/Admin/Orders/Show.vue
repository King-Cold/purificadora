<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

// Modal de Confirmación
const showConfirmModal = ref(false);
const confirmForm = useForm({
    comment: '',
});

const openConfirmModal = () => {
    confirmForm.clearErrors();
    confirmForm.comment = '';
    showConfirmModal.value = true;
};

const closeConfirmModal = () => {
    if (confirmForm.processing) return;
    showConfirmModal.value = false;
};

const submitConfirmation = () => {
    confirmForm.post(route('admin.orders.confirm', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmModal.value = false;
        },
    });
};

// Modal de Cancelación
const showCancelModal = ref(false);
const cancelForm = useForm({
    reason: '',
});

const openCancelModal = () => {
    cancelForm.clearErrors();
    cancelForm.reason = '';
    showCancelModal.value = true;
};

const closeCancelModal = () => {
    if (cancelForm.processing) return;
    showCancelModal.value = false;
};

const submitCancellation = () => {
    cancelForm.post(route('admin.orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCancelModal.value = false;
        },
    });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-amber-50 text-amber-900 border-amber-300';
        case 'confirmed':
            return 'bg-blue-50 text-blue-900 border-blue-300';
        case 'cancelled':
            return 'bg-rose-50 text-rose-900 border-rose-300';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-300';
    }
};

const getStatusIconClass = (status) => {
    switch (status) {
        case 'pending':
            return 'fa-regular fa-clock text-amber-600';
        case 'confirmed':
            return 'fa-solid fa-circle-check text-blue-700';
        case 'cancelled':
            return 'fa-solid fa-ban text-rose-600';
        default:
            return 'fa-solid fa-circle text-slate-400';
    }
};
</script>

<template>
    <Head :title="`Pedido ${order.folio} - Puritronic`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3.5">
                    <Link
                        :href="route('admin.orders.index')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0 cursor-pointer"
                        title="Volver al listado de pedidos"
                    >
                        <i class="fa-solid fa-arrow-left text-sm"></i>
                    </Link>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                                Pedido {{ order.folio }}
                            </h2>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border',
                                    getStatusBadgeClass(order.status)
                                ]"
                            >
                                <i :class="getStatusIconClass(order.status)" class="text-[11px]"></i>
                                <span>{{ order.status_label }}</span>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Registrado el {{ order.created_at }} &bull; Botellones de 20 Litros
                        </p>
                    </div>
                </div>

                <!-- Botones de Acción Administrativa -->
                <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
                    <!-- Botón Confirmar (Únicamente para pedidos Pendientes) -->
                    <button
                        v-if="order.can_confirm && order.is_pending"
                        type="button"
                        @click="openConfirmModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-check-circle text-sky-400"></i>
                        <span>Confirmar Pedido</span>
                    </button>

                    <!-- Botón Cancelar (Permitido para pedidos Pendientes y Confirmados) -->
                    <button
                        v-if="order.can_cancel && (order.is_pending || order.is_confirmed)"
                        type="button"
                        @click="openCancelModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-solid fa-ban"></i>
                        <span>Cancelar Pedido</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. ALERTAS FLASH (ÉXITO O ERROR) -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 sm:p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-emerald-950">Operación Exitosa</h4>
                        <p class="text-xs sm:text-sm text-emerald-800 mt-0.5 leading-relaxed font-medium">
                            {{ $page.props.flash.success }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="$page.props.flash?.error"
                    class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-rose-950">Atención</h4>
                        <p class="text-xs sm:text-sm text-rose-800 mt-0.5 leading-relaxed font-medium">
                            {{ $page.props.flash.error }}
                        </p>
                    </div>
                </div>

                <!-- 2. AVISO DE CANCELACIÓN (SI EL PEDIDO ESTÁ CANCELADO) -->
                <div
                    v-if="order.is_cancelled"
                    class="p-4 sm:p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-xs flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <i class="fa-solid fa-ban text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-rose-950">Este pedido se encuentra cancelado</h4>
                        <p v-if="order.cancellation_reason" class="text-xs sm:text-sm text-rose-800 mt-1 leading-relaxed">
                            <span class="font-bold">Motivo registrado:</span> "{{ order.cancellation_reason }}"
                        </p>
                        <p class="text-xs text-rose-700 mt-1">
                            Los pedidos cancelados son inmutables y no admiten modificaciones posteriores ni cambios de estado.
                        </p>
                    </div>
                </div>

                <!-- 3. CUADRÍCULA DE DETALLES PRINCIPALES -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- COLUMNA IZQUIERDA: CLIENTE Y REGISTRO (1 COL) -->
                    <div class="space-y-6">

                        <!-- TARJETA: INFORMACIÓN DEL CLIENTE -->
                        <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Cliente Solicitante
                                </h3>
                                <Link
                                    v-if="order.customer"
                                    :href="route('admin.customers.show', order.customer.id)"
                                    class="text-xs font-semibold text-blue-900 hover:text-blue-700 transition"
                                >
                                    Ver perfil <i class="fa-solid fa-arrow-up-right-from-square text-[10px] ml-0.5"></i>
                                </Link>
                            </div>

                            <div v-if="order.customer" class="space-y-3">
                                <div>
                                    <h4 class="text-base font-bold text-slate-900">{{ order.customer.name }}</h4>
                                    <p v-if="order.customer.nickname" class="text-xs text-slate-600 font-medium">
                                        "{{ order.customer.nickname }}"
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ order.customer.code }}
                                    </span>
                                    <span
                                        :class="[
                                            'px-2.5 py-1 rounded-lg text-xs font-bold border',
                                            order.customer.has_account
                                                ? 'bg-blue-50 text-blue-900 border-blue-200'
                                                : 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                        ]"
                                    >
                                        {{ order.customer.has_account ? 'Con Cuenta' : 'Sin Cuenta' }}
                                    </span>
                                </div>

                                <div v-if="order.customer.phone" class="text-xs text-slate-600 flex items-center gap-2 pt-1">
                                    <i class="fa-solid fa-phone text-slate-400 text-xs"></i>
                                    <span>{{ order.customer.phone }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: USUARIO QUE REGISTRÓ EL PEDIDO -->
                        <div class="bg-white rounded-2xl p-5 shadow-xs border border-slate-200/80 space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 pb-3 border-b border-slate-100">
                                Registro del Pedido
                            </h3>

                            <div v-if="order.creator" class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-sm shrink-0">
                                    <i class="fa-solid fa-user-shield text-slate-500"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                        Registrado por
                                    </div>
                                    <div class="text-sm font-bold text-slate-900 truncate">
                                        {{ order.creator.name }}
                                    </div>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ order.creator.role }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500">
                                Registrado directamente por el cliente desde el portal web.
                            </div>
                        </div>

                    </div>

                    <!-- COLUMNA CENTRAL Y DERECHA: BOTELLONES, DOMICILIO E HISTORIAL (2 COLS) -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- TARJETA: BOTELLONES SOLICITADOS -->
                        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Botellones de 20 Litros Solicitados
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900">
                                    Capacidad 20L
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200/80 text-center">
                                    <span class="text-xs font-bold text-blue-900 uppercase tracking-wider block">Fijos</span>
                                    <span class="text-2xl font-black text-blue-950 mt-1 block">{{ order.fixed_quantity }}</span>
                                    <span class="text-[11px] text-blue-800">Botellones 20L</span>
                                </div>

                                <div class="p-4 rounded-xl bg-sky-50/70 border border-sky-200/80 text-center">
                                    <span class="text-xs font-bold text-sky-900 uppercase tracking-wider block">De Cambio</span>
                                    <span class="text-2xl font-black text-sky-950 mt-1 block">{{ order.exchange_quantity }}</span>
                                    <span class="text-[11px] text-sky-800">Botellones 20L</span>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-100/80 border border-slate-200 text-center">
                                    <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Total Solicitado</span>
                                    <span class="text-2xl font-black text-slate-900 mt-1 block">{{ order.total_bottles }}</span>
                                    <span class="text-[11px] text-slate-600">Botellones de 20L</span>
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div v-if="order.notes" class="pt-3 border-t border-slate-100">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">
                                    Observaciones de entrega:
                                </span>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-700 italic">
                                    "{{ order.notes }}"
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: CONTACTO Y DOMICILIO HISTÓRICO -->
                        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Domicilio y Contacto Histórico del Pedido
                                </h3>
                                <span class="text-[11px] text-slate-400 font-medium">
                                    Instantánea del pedido
                                </span>
                            </div>

                            <div class="space-y-3 text-xs sm:text-sm">
                                <div class="flex items-start gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fa-solid fa-location-dot text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-bold text-slate-900">
                                            {{ order.address.full_address || 'Sin dirección registrada' }}
                                        </div>
                                        <div v-if="order.address.zone_label" class="mt-1">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-900">
                                                Zona {{ order.address.zone_label }}
                                            </span>
                                        </div>
                                        <div v-if="order.address.reference" class="text-xs text-slate-500 mt-1">
                                            <span class="font-semibold text-slate-700">Referencia:</span> {{ order.address.reference }}
                                        </div>
                                        <div v-if="order.address.between_streets" class="text-xs text-slate-500">
                                            <span class="font-semibold text-slate-700">Entre calles:</span> {{ order.address.between_streets }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="order.address.recipient_name || order.address.recipient_phone" class="pt-2 border-t border-slate-100 flex flex-wrap gap-4 text-xs text-slate-600">
                                    <div v-if="order.address.recipient_name">
                                        <span class="text-slate-400">Receptor:</span> <span class="font-semibold text-slate-800">{{ order.address.recipient_name }}</span>
                                    </div>
                                    <div v-if="order.address.recipient_phone">
                                        <span class="text-slate-400">Teléfono contacto:</span> <span class="font-semibold text-slate-800">{{ order.address.recipient_phone }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TARJETA: HISTORIAL CRONOLÓGICO DE ESTADOS -->
                        <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-xs border border-slate-200/80 space-y-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 pb-3 border-b border-slate-100">
                                Historial de Estados y Responsables
                            </h3>

                            <div v-if="order.history && order.history.length > 0" class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                                <div
                                    v-for="(hist, idx) in order.history"
                                    :key="hist.id || idx"
                                    class="relative text-xs sm:text-sm"
                                >
                                    <!-- Punto en la línea de tiempo -->
                                    <span class="absolute -left-6 top-1 h-5 w-5 rounded-full bg-white border-2 border-blue-900 flex items-center justify-center">
                                        <span class="h-2 w-2 rounded-full bg-blue-900"></span>
                                    </span>

                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                        <div class="font-bold text-slate-900 flex items-center gap-2">
                                            <span>Estado: {{ hist.new_status_label }}</span>
                                            <span v-if="hist.previous_status_label" class="text-xs text-slate-400 font-normal">
                                                (anterior: {{ hist.previous_status_label }})
                                            </span>
                                        </div>
                                        <span class="text-xs text-slate-400 font-mono">{{ hist.created_at }}</span>
                                    </div>

                                    <div class="text-xs text-slate-600 mt-1 flex flex-wrap items-center gap-2">
                                        <span>Responsable: <span class="font-semibold text-slate-800">{{ hist.user_name }}</span></span>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-600 font-medium">
                                            {{ hist.user_role }}
                                        </span>
                                    </div>

                                    <div v-if="hist.reason" class="mt-2 p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 italic">
                                        "{{ hist.reason }}"
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-xs text-slate-500 italic py-2">
                                No hay registros adicionales de cambio de estado.
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMAR PEDIDO (SÓLO PARA ESTADO PENDIENTE) -->
        <!-- ========================================================================= -->
        <transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showConfirmModal"
                class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                @click.self="closeConfirmModal"
            >
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-scale-in">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-blue-100 text-blue-900 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-clipboard-check text-blue-900"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Confirmar Pedido {{ order.folio }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                Transición de Pendiente a Confirmado
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200/70 text-xs text-blue-900 leading-relaxed">
                        ¿Deseas confirmar el pedido con folio <span class="font-bold">{{ order.folio }}</span>?
                        El pedido pasará a estado Confirmado para su posterior atención y entrega.
                    </div>

                    <div class="space-y-1.5">
                        <label for="confirm_comment" class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Comentario opcional
                        </label>
                        <textarea
                            id="confirm_comment"
                            v-model="confirmForm.comment"
                            rows="2"
                            maxlength="500"
                            placeholder="Comentario o nota administrativa interna (opcional)..."
                            class="w-full rounded-xl border-slate-200 text-xs sm:text-sm focus:border-blue-900 focus:ring-blue-900 transition"
                        ></textarea>
                        <InputError :message="confirmForm.errors.comment" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeConfirmModal"
                            :disabled="confirmForm.processing"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="submitConfirmation"
                            :disabled="confirmForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-50 transition shadow-xs cursor-pointer"
                        >
                            <i v-if="confirmForm.processing" class="fa-solid fa-spinner fa-spin text-sky-400"></i>
                            <i v-else class="fa-solid fa-check text-sky-400"></i>
                            <span>{{ confirmForm.processing ? 'Confirmando...' : 'Sí, Confirmar Pedido' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ========================================================================= -->
        <!-- MODAL: CANCELAR PEDIDO (MOTIVO OBLIGATORIO) -->
        <!-- ========================================================================= -->
        <transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showCancelModal"
                class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
                @click.self="closeCancelModal"
            >
                <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-scale-in">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-triangle-exclamation text-rose-700"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Cancelar Pedido {{ order.folio }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                Acción definitiva &bull; Registro de motivo obligatorio
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-rose-50/70 border border-rose-200/80 text-xs text-rose-900 leading-relaxed">
                        ¿Estás seguro de cancelar este pedido? Esta acción es definitiva: el pedido no podrá volver a activarse y liberará el bloqueo de pedido activo para el cliente.
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="cancel_reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Motivo de cancelación <span class="text-rose-600">*</span>
                            </label>
                            <span class="text-[11px] text-slate-400">
                                {{ (cancelForm.reason || '').length }} / 500
                            </span>
                        </div>
                        <textarea
                            id="cancel_reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Explica el motivo por el cual se cancela el pedido (mínimo 3 caracteres)..."
                            class="w-full rounded-xl border-slate-200 text-xs sm:text-sm focus:border-rose-600 focus:ring-rose-600 transition"
                        ></textarea>
                        <InputError :message="cancelForm.errors.reason" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="closeCancelModal"
                            :disabled="cancelForm.processing"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer"
                        >
                            Volver
                        </button>
                        <button
                            type="button"
                            @click="submitCancellation"
                            :disabled="cancelForm.processing || !cancelForm.reason || cancelForm.reason.trim().length < 3"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition shadow-xs cursor-pointer"
                        >
                            <i v-if="cancelForm.processing" class="fa-solid fa-spinner fa-spin"></i>
                            <i v-else class="fa-solid fa-ban"></i>
                            <span>{{ cancelForm.processing ? 'Cancelando...' : 'Confirmar Cancelación' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

    </AuthenticatedLayout>
</template>
