<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    nickname: '',
    phone: '',
    secondary_phone: '',
    email: '',
    notes: '',
});

const submit = () => {
    form.post(route('admin.customers.store'));
};
</script>

<template>
    <Head title="Nuevo Cliente Particular - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('admin.customers.index')" class="hover:text-blue-900 transition">
                            Clientes
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">Nuevo Cliente</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Registrar Cliente Particular
                    </h2>
                    <p class="text-sm text-gray-500">
                        Alta administrativa de cliente sin cuenta de acceso al portal (atención telefónica o en ruta)
                    </p>
                </div>

                <Link
                    :href="route('admin.customers.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition self-start sm:self-auto shadow-sm"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver al directorio</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- Aviso Informativo de Registro Administrativo -->
                        <div class="p-4 rounded-xl bg-blue-50/70 border border-blue-200/80 text-blue-950 text-xs flex items-start gap-3">
                            <svg class="w-5 h-5 text-blue-700 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="leading-relaxed">
                                <span class="font-bold block text-sm mb-0.5">Registro de Cliente sin Cuenta Web</span>
                                Este formulario registra exclusivamente el perfil de cliente para pedidos telefónicos y entregas en ruta. El código de cliente (ej. CLI-XXXX) se generará automáticamente. No se crean credenciales de usuario ni contraseñas.
                            </div>
                        </div>

                        <!-- Sección: Datos del Cliente -->
                        <div class="border-b border-gray-100 pb-2">
                            <h3 class="text-base font-semibold text-gray-900">
                                Información Personal de la Persona Particular
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Los campos marcados con asterisco (*) son requeridos
                            </p>
                        </div>

                        <!-- Nombre Completo y Apodo -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                            <!-- Nombre Completo -->
                            <div class="sm:col-span-7">
                                <InputLabel for="name" value="Nombre completo *" class="font-medium text-gray-700" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    placeholder="Ej. Juan Carlos Pérez Morales"
                                    autocomplete="name"
                                />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                            </div>

                            <!-- Apodo / Alias Comunitario -->
                            <div class="sm:col-span-5">
                                <div class="flex items-center justify-between">
                                    <InputLabel for="nickname" value="Apodo / Alias" class="font-medium text-gray-700" />
                                    <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                                </div>
                                <TextInput
                                    id="nickname"
                                    type="text"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.nickname"
                                    placeholder="Ej. Don Juanito, El Güero..."
                                />
                                <InputError class="mt-1.5" :message="form.errors.nickname" />
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Facilita la identificación comunitaria para los repartidores.
                                </p>
                            </div>
                        </div>

                        <!-- Teléfonos de Contacto -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Teléfono Principal -->
                            <div>
                                <InputLabel for="phone" value="Teléfono principal *" class="font-medium text-gray-700" />
                                <TextInput
                                    id="phone"
                                    type="tel"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.phone"
                                    required
                                    placeholder="Ej. 9991234567"
                                    autocomplete="tel"
                                />
                                <InputError class="mt-1.5" :message="form.errors.phone" />
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Para llamadas y coordinación de entregas.
                                </p>
                            </div>

                            <!-- Teléfono Secundario -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <InputLabel for="secondary_phone" value="Teléfono secundario / WhatsApp" class="font-medium text-gray-700" />
                                    <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                                </div>
                                <TextInput
                                    id="secondary_phone"
                                    type="tel"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.secondary_phone"
                                    placeholder="Ej. 9997654321"
                                />
                                <InputError class="mt-1.5" :message="form.errors.secondary_phone" />
                            </div>
                        </div>

                        <!-- Correo Electrónico (Opcional) -->
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="email" value="Correo electrónico" class="font-medium text-gray-700" />
                                <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                            </div>
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                v-model="form.email"
                                placeholder="cliente@correo.com"
                                autocomplete="email"
                            />
                            <InputError class="mt-1.5" :message="form.errors.email" />
                            <p class="text-[11px] text-gray-400 mt-1">
                                Opcional, para envío de comprobantes de servicio si el cliente lo solicita.
                            </p>
                        </div>

                        <!-- Observaciones / Notas Internas -->
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="notes" value="Observaciones o notas de entrega" class="font-medium text-gray-700" />
                                <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                            </div>
                            <textarea
                                id="notes"
                                rows="3"
                                v-model="form.notes"
                                class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900 placeholder-gray-400"
                                placeholder="Ej. El cliente suele estar por las tardes; portón verde junto a la tienda de la esquina..."
                            ></textarea>
                            <InputError class="mt-1.5" :message="form.errors.notes" />
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                            <Link
                                :href="route('admin.customers.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none transition shadow-sm"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2 bg-blue-900 border border-transparent rounded-lg font-medium text-sm text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg
                                    v-if="form.processing"
                                    class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar cliente' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
