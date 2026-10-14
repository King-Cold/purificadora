<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name: '',
    nickname: '',
    phone: '',
    secondary_phone: '',
    email: '',
    fixed_bottles_count: 0,
    exchange_bottles_count: 0,
    notes: '',
});

const totalBottles = computed(() => {
    const f = Number.parseInt(form.fixed_bottles_count, 10) || 0;
    const c = Number.parseInt(form.exchange_bottles_count, 10) || 0;
    return Math.max(0, f) + Math.max(0, c);
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
                        Alta administrativa de persona particular (vecino) para entrega de garrafones sin cuenta web
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
                                <span class="font-bold block text-sm mb-0.5">Registro de Persona Particular para Reparto</span>
                                Registro vecinal para entrega directa de garrafones de agua. Se generará un código único (CLI-XXXX). Solo se requiere un medio para identificar a la persona (su nombre o su apodo comunitario) y un teléfono de contacto.
                            </div>
                        </div>

                        <!-- Sección: Datos del Cliente -->
                        <div class="border-b border-gray-100 pb-2">
                            <h3 class="text-base font-semibold text-gray-900">
                                Datos Personales de Identificación
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Puede ingresar el nombre completo, el apodo comunitario o ambos. Al menos uno es necesario.
                            </p>
                        </div>

                        <!-- Nombre Completo y Apodo -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                            <!-- Nombre Completo (Opcional) -->
                            <div class="sm:col-span-7">
                                <div class="flex items-center justify-between">
                                    <InputLabel for="name" value="Nombre completo" class="font-medium text-gray-700" />
                                    <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                                </div>
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.name"
                                    autofocus
                                    placeholder="Ej. Juan Carlos Pérez Morales"
                                    autocomplete="name"
                                />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Si no se conoce el nombre completo, puede registrarse solo con su apodo.
                                </p>
                            </div>

                            <!-- Apodo / Alias Comunitario -->
                            <div class="sm:col-span-5">
                                <div class="flex items-center justify-between">
                                    <InputLabel for="nickname" value="Apodo / Alias vecinal" class="font-medium text-gray-700" />
                                    <span class="text-[11px] text-gray-400 font-medium">Opcional</span>
                                </div>
                                <TextInput
                                    id="nickname"
                                    type="text"
                                    class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                    v-model="form.nickname"
                                    placeholder="Ej. Don Beto, Doña Mary..."
                                />
                                <InputError class="mt-1.5" :message="form.errors.nickname" />
                                <p class="text-[11px] text-gray-400 mt-1">
                                    Cómo se conoce a la persona en la colonia.
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
                                placeholder="vecino@correo.com"
                                autocomplete="email"
                            />
                            <InputError class="mt-1.5" :message="form.errors.email" />
                            <p class="text-[11px] text-gray-400 mt-1">
                                Opcional, solo si la persona solicita comprobantes por correo.
                            </p>
                        </div>

                        <!-- Sección: Botellones del Cliente -->
                        <div class="border-t border-gray-100 pt-5">
                            <div class="border-b border-gray-100 pb-2 mb-4">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900">
                                            Botellones del cliente
                                        </h3>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Registre cuántos botellones de cada tipo tiene el cliente en su poder (puede tener de ambos tipos)
                                        </p>
                                    </div>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold self-start sm:self-auto shadow-2xs">
                                        <span class="text-gray-600">Total en posesión:</span>
                                        <span class="text-sm font-bold text-blue-950 font-mono">{{ totalBottles }}</span>
                                        <span class="text-gray-600">garrafones</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <!-- Botellones Fijos -->
                                <div class="bg-gray-50/70 p-4 rounded-xl border border-gray-200 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <InputLabel for="fixed_bottles_count" value="Botellones fijos" class="font-bold text-gray-900" />
                                            <span class="text-[11px] px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-semibold">Rotulados con su nombre</span>
                                        </div>
                                        <TextInput
                                            id="fixed_bottles_count"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                            v-model.number="form.fixed_bottles_count"
                                            required
                                        />
                                        <InputError class="mt-1.5" :message="form.errors.fixed_bottles_count" />
                                    </div>
                                    <p class="text-xs text-gray-600 mt-3 leading-relaxed">
                                        Pertenecen al cliente y están identificados con su nombre. Se recogen, lavan, llenan y se le devuelve exactamente el mismo envase.
                                    </p>
                                </div>

                                <!-- Botellones de Cambio -->
                                <div class="bg-gray-50/70 p-4 rounded-xl border border-gray-200 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <InputLabel for="exchange_bottles_count" value="Botellones de cambio" class="font-bold text-gray-900" />
                                            <span class="text-[11px] px-2 py-0.5 rounded bg-sky-100 text-sky-900 font-semibold">Intercambiables</span>
                                        </div>
                                        <TextInput
                                            id="exchange_bottles_count"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                            v-model.number="form.exchange_bottles_count"
                                            required
                                        />
                                        <InputError class="mt-1.5" :message="form.errors.exchange_bottles_count" />
                                    </div>
                                    <p class="text-xs text-gray-600 mt-3 leading-relaxed">
                                        Envases sin identificación personal. Se entregan vacíos y se intercambian de inmediato por otro botellón lleno durante la visita del repartidor.
                                    </p>
                                </div>
                            </div>
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
