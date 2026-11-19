<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/AdminPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const form = useForm({
    name: '',
    nickname: '',
    notes: '',
});

const submit = () => {
    form.post(route('admin.customers.store'));
};
</script>

<template>
    <Head title="Nuevo cliente" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <AdminPageHeader
                title="Registrar Cliente"
                subtitle="Registro administrativo para control de garrafones y reparto."
                icon="fa-solid fa-user-plus"
                accent="green"
                :breadcrumbs="[
                    { label: 'Administración', href: route('dashboard') },
                    { label: 'Clientes', href: route('admin.customers.index') },
                    { label: 'Nuevo Cliente' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="route('admin.customers.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-medium text-[#777386] hover:text-[#211B35] bg-white hover:bg-[#F5F4F8] border border-[#E7E4ED] rounded-xl transition duration-150 shadow-2xs"
                    >
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Volver al directorio</span>
                    </Link>
                </template>
            </AdminPageHeader>
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

                        <!-- SECCIÓN 2: MODALIDAD LIBRE PARA CLIENTES SIN CUENTA -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-3 text-xs text-slate-600">
                            <div class="w-8 h-8 rounded-lg bg-[#EAF4FB] text-[#0071BA] flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </div>
                            <div>
                                <span class="font-bold text-slate-900 block">Sin límite ni modalidad fija en ficha</span>
                                <p class="mt-0.5 leading-relaxed">
                                    Al registrarse sin cuenta de usuario, este cliente no necesita capturar botellones fijos ni de cambio aquí. En cada servicio al paso el repartidor podrá elegir libremente Fijos o Cambios según lo que pida.
                                </p>
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
