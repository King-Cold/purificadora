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
    cancelForm.post(route('cliente.orders.cancel', props.order.id), {
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
                        :href="route('cliente.orders.index')"
                        class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0"
                        title="Volver a mis pedidos"
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

                <!-- Botón Cancelar (Exclusivo para pedidos Pendientes) -->
                <div v-if="order.can_cancel && order.is_pending" class="flex items-center gap-2 self-start sm:self-auto">
                    <button
                        type="button"
                        @click="openCancelModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition shadow-2xs cursor-pointer"
                    >
                        <i class="fa-solid fa-ban"></i>
                        <span>Cancelar Pedido</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

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
                    class="p-5 rounded-2xl bg-rose-50 border border-rose-200 shadow-xs text-rose-950 flex items-start gap-3.5"
                >
                    <div class="h-10 w-10 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 text-base">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div class="flex-1 space-y-1">
                        <h4 class="text-sm font-bold text-rose-950 uppercase tracking-wider">
                            Pedido Cancelado
                        </h4>
                        <p class="text-xs sm:text-sm text-rose-900 leading-relaxed">
                            <span class="font-semibold">Motivo:</span> {{ order.cancellation_reason || 'Sin motivo registrado' }}
                        </p>
                        <p class="text-xs text-rose-800 pt-0.5">
                            Este pedido ya no está en curso. Puedes registrar un nuevo pedido cuando lo requieras.
                        </p>
                    </div>
                </div>

                <!-- 3. CANTIDADES SOLICITADAS (EXCLUSIVAMENTE 20 LITROS) -->
                <div class="bg-white rounded-2xl p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                            <i class="fa-solid fa-bottle-water text-blue-900"></i>
                            <span>Botellones Solicitados (20 Litros)</span>
                        </h3>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                            Presentación: 20L
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Fijos -->
                        <div class="p-4 rounded-xl bg-blue-50/50 border border-blue-200/70 text-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-900 block">
                                Botellones Fijos
                            </span>
                            <span class="text-3xl font-extrabold text-blue-950 font-mono mt-1 block">
                                {{ order.fixed_quantity }}
                            </span>
                            <span class="text-[11px] text-slate-500 mt-0.5 block">
                                Envases rotulados de 20L
                            </span>
                        </div>

                        <!-- Cambio -->
                        <div class="p-4 rounded-xl bg-sky-50/50 border border-sky-200/70 text-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-800 block">
                                Botellones de Cambio
                            </span>
                            <span class="text-3xl font-extrabold text-sky-950 font-mono mt-1 block">
                                {{ order.exchange_quantity }}
                            </span>
                            <span class="text-[11px] text-slate-500 mt-0.5 block">
                                Intercambio estándar de 20L
                            </span>
                        </div>

                        <!-- Total -->
                        <div class="p-4 rounded-xl bg-slate-900 text-white text-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-sky-200 block">
                                Total de Botellones
                            </span>
                            <span class="text-3xl font-extrabold text-white font-mono mt-1 block">
                                {{ order.total_bottles }}
                            </span>
                            <span class="text-[11px] text-slate-300 mt-0.5 block">
                                Total a recibir en domicilio
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 4. DOMICILIO Y CONTACTO HISTÓRICOS INMUTABLES -->
                <div class="bg-white rounded-2xl p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-house-chimney text-emerald-700"></i>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">
                                Domicilio de Entrega Histórico
                            </h3>
                        </div>
                        <span v-if="order.address?.zone_label" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-900 border border-blue-200">
                            {{ order.address.zone_label }}
                        </span>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                                    Receptor
                                </span>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ order.address?.recipient_name || 'Cliente' }}
                                </p>
                            </div>
                            <div v-if="order.address?.recipient_phone">
                                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                                    Teléfono de Contacto
                                </span>
                                <p class="text-sm font-mono font-semibold text-slate-900 mt-0.5">
                                    {{ order.address.recipient_phone }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">
                                Dirección Registrada
                            </span>
                            <p class="text-sm font-bold text-slate-900">
                                {{ order.address?.full_address }}
                            </p>
                            <p v-if="order.address?.between_streets" class="text-xs text-slate-600 pt-0.5">
                                <span class="font-medium text-slate-500">Entre calles:</span> {{ order.address.between_streets }}
                            </p>
                            <p v-if="order.address?.reference" class="text-xs text-slate-600">
                                <span class="font-medium text-slate-500">Referencias:</span> {{ order.address.reference }}
                            </p>
                        </div>

                        <p class="text-[11px] text-slate-400 pt-2 border-t border-slate-100 flex items-center gap-1.5">
                            <i class="fa-solid fa-lock text-[10px]"></i>
                            <span>Copia histórica inmutable guardada al momento de crear el pedido.</span>
                        </p>
                    </div>
                </div>

                <!-- 5. OBSERVACIONES -->
                <div class="bg-white rounded-2xl p-6 shadow-xs border border-slate-200/80 space-y-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                        <i class="fa-regular fa-comment-dots text-slate-500"></i>
                        <span>Observaciones de Entrega</span>
                    </h3>
                    <p v-if="order.notes" class="text-sm text-slate-800 leading-relaxed bg-slate-50 p-4 rounded-xl border border-slate-200/70">
                        {{ order.notes }}
                    </p>
                    <p v-else class="text-xs text-slate-400 italic">
                        Sin observaciones o instrucciones adicionales registradas en este pedido.
                    </p>
                </div>

                <!-- 6. HISTORIAL CRONOLÓGICO DE ESTADOS (EN ESPAÑOL) -->
                <div class="bg-white rounded-2xl p-6 shadow-xs border border-slate-200/80 space-y-4">
                    <div class="border-b border-slate-100 pb-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                            <i class="fa-solid fa-timeline text-blue-900"></i>
                            <span>Historial de Estados</span>
                        </h3>
                    </div>

                    <div class="space-y-4 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-slate-200">
                        <div
                            v-for="(historyItem, idx) in order.history"
                            :key="historyItem.id || idx"
                            class="relative flex items-start gap-4 pl-1"
                        >
                            <div class="h-7 w-7 rounded-full bg-white border-2 border-blue-900 text-blue-900 flex items-center justify-center shrink-0 z-10 text-[10px] shadow-2xs">
                                <i :class="getStatusIconClass(historyItem.new_status)"></i>
                            </div>

                            <div class="flex-1 bg-slate-50 p-3.5 rounded-xl border border-slate-200/70 space-y-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-slate-900">
                                        Estado: <strong class="text-blue-950">{{ historyItem.new_status_label }}</strong>
                                    </span>
                                    <span class="text-[11px] font-mono text-slate-400">
                                        {{ historyItem.created_at }}
                                    </span>
                                </div>
                                <p v-if="historyItem.reason" class="text-xs text-slate-600">
                                    {{ historyItem.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. BOTONES DE NAVEGACIÓN INFERIOR -->
                <div class="flex items-center justify-between pt-2">
                    <Link
                        :href="route('cliente.orders.index')"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition"
                    >
                        &larr; Volver a Mis Pedidos
                    </Link>

                    <Link
                        :href="route('cliente.orders.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs"
                    >
                        <i class="fa-solid fa-plus-circle text-sky-400"></i>
                        <span>Nuevo Pedido</span>
                    </Link>
                </div>

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODAL DE CANCELACIÓN DE PEDIDO PENDIENTE -->
        <!-- ========================================================================= -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            aria-labelledby="modal-cancel-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-scaleIn">
                <div class="flex items-start gap-4">
                    <div class="h-11 w-11 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0 text-lg">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 id="modal-cancel-title" class="text-base font-bold text-slate-900">
                            ¿Deseas cancelar el pedido {{ order.folio }}?
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Esta acción cancelará la entrega de tus {{ order.total_bottles }} botellón(es). No se alterarán los conteos de tu cuenta.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitCancellation" class="space-y-4">
                    <div>
                        <label for="cancel-reason" class="text-xs font-bold text-slate-700 block mb-1">
                            Motivo de cancelación <span class="text-rose-600">*</span>
                        </label>
                        <textarea
                            id="cancel-reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            maxlength="500"
                            required
                            placeholder="Ej. Salí de casa, ya no requiero los garrafones hoy, error en la cantidad..."
                            class="w-full rounded-xl border-slate-300 text-sm focus:border-rose-600 focus:ring-rose-600"
                            :disabled="cancelForm.processing"
                        ></textarea>
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                            <span>El motivo es obligatorio para registrar la cancelación.</span>
                            <span class="font-mono">{{ cancelForm.reason.length }} / 500</span>
                        </div>
                        <InputError :message="cancelForm.errors.reason" class="mt-1" />
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="closeCancelModal"
                            :disabled="cancelForm.processing"
                            class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                        >
                            No cancelar
                        </button>

                        <button
                            type="submit"
                            :disabled="cancelForm.processing || !cancelForm.reason.trim()"
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i v-if="cancelForm.processing" class="fa-solid fa-circle-notch fa-spin"></i>
                            <i v-else class="fa-solid fa-ban"></i>
                            <span>{{ cancelForm.processing ? 'Cancelando...' : 'Sí, cancelar pedido' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
