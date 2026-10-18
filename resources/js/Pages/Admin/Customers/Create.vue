<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const form = useForm({
    name: '',
    nickname: '',
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

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Clientes', href: route('admin.customers.index'), icon: 'fa-solid fa-address-book' },
                            { label: 'Nuevo Cliente', icon: 'fa-solid fa-user-plus' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold shadow-xs shrink-0">
                            <i class="fa-solid fa-user-plus text-base"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold leading-tight text-slate-900">
                                Registrar Cliente
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-500">
                                Registro administrativo para control de garrafones y reparto
                            </p>
                        </div>
                    </div>
                </div>

                <Link
                    :href="route('admin.customers.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition self-start sm:self-auto shadow-xs"
                >
                    <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                    <span>Volver al directorio</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Tarjeta Principal de Registro -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    
                    <!-- Encabezado simple -->
                    <div class="bg-slate-50 border-b border-slate-200 px-6 sm:px-8 py-4">
                        <h3 class="font-bold text-sm text-slate-900">
                            Alta de Cliente Sin Cuenta
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Se asignará automáticamente un código único (<span class="font-mono font-bold text-slate-700">CLI-XXXX</span>). Ingrese al menos el nombre o apodo para identificar al cliente.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-7">

                        <!-- SECCIÓN 1: IDENTIFICACIÓN -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200">
                                <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                    Identificación
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-5">
                                <!-- Nombre -->
                                <div class="sm:col-span-7">
                                    <div class="flex items-center justify-between">
                                        <InputLabel for="name" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                            Nombre
                                        </InputLabel>
                                        <span class="text-[11px] text-slate-400 font-medium">Opcional</span>
                                    </div>
                                    <TextInput
                                        id="name"
                                        type="text"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.name"
                                        autofocus
                                        placeholder="Ej. Juan Carlos Pérez Morales"
                                        autocomplete="name"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.name" />
                                </div>

                                <!-- Apodo -->
                                <div class="sm:col-span-5">
                                    <div class="flex items-center justify-between">
                                        <InputLabel for="nickname" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                            Apodo
                                        </InputLabel>
                                        <span class="text-[11px] text-slate-400 font-medium">Opcional</span>
                                    </div>
                                    <TextInput
                                        id="nickname"
                                        type="text"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.nickname"
                                        placeholder="Ej. Don Beto, Doña Mary..."
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.nickname" />
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: BOTELLONES -->
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-2 border-b border-slate-200 gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-bottle-water"></i>
                                    </div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                        Cantidad de Botellones
                                    </h4>
                                </div>

                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-blue-900 text-white text-xs font-bold self-start sm:self-auto">
                                    <span>Total:</span>
                                    <span class="text-sm font-mono font-bold">{{ totalBottles }}</span>
                                    <span>garrafones</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <!-- Botellones Fijos -->
                                <div class="bg-blue-50/70 p-4 rounded-xl border border-blue-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <InputLabel for="fixed_bottles_count" class="font-bold text-slate-900 text-xs">
                                            Cantidad de botellones fijos
                                        </InputLabel>
                                        <span class="text-[11px] px-2 py-0.5 rounded bg-blue-600 text-white font-semibold">
                                            Rotulados
                                        </span>
                                    </div>
                                    <TextInput
                                        id="fixed_bottles_count"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 font-semibold"
                                        v-model.number="form.fixed_bottles_count"
                                        required
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.fixed_bottles_count" />
                                </div>

                                <!-- Botellones de Cambio -->
                                <div class="bg-sky-50/70 p-4 rounded-xl border border-sky-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <InputLabel for="exchange_bottles_count" class="font-bold text-slate-900 text-xs">
                                            Cantidad de botellones de cambio
                                        </InputLabel>
                                        <span class="text-[11px] px-2 py-0.5 rounded bg-sky-600 text-white font-semibold">
                                            Intercambiables
                                        </span>
                                    </div>
                                    <TextInput
                                        id="exchange_bottles_count"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 font-semibold"
                                        v-model.number="form.exchange_bottles_count"
                                        required
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.exchange_bottles_count" />
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: OBSERVACIONES O NOTAS DE REPARTO -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-note-sticky"></i>
                                    </div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                        Observaciones o Notas de Reparto
                                    </h4>
                                </div>
                                <span class="text-[11px] font-medium text-slate-500">
                                    Opcional
                                </span>
                            </div>

                            <div>
                                <textarea
                                    id="notes"
                                    rows="3"
                                    v-model="form.notes"
                                    class="block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 placeholder-slate-400"
                                    placeholder="Ej. Casa de rejas blancas, tocar portón; acostumbra pedir los lunes..."
                                ></textarea>
                                <InputError class="mt-1.5" :message="form.errors.notes" />
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN -->
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-200">
                            <Link
                                :href="route('admin.customers.index')"
                                class="w-full sm:w-auto text-center px-5 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition shadow-xs"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-900 hover:bg-blue-800 active:bg-blue-950 border border-transparent rounded-xl font-bold text-sm text-white shadow-xs focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i v-if="!form.processing" class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>{{ form.processing ? 'Guardando cliente...' : 'Guardar cliente' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
