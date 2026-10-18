<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    address: {
        type: Object,
        default: null,
    },
});

const deleteAddress = () => {
    if (confirm('¿Estás seguro de que deseas eliminar este domicilio? Tu cuenta quedará sin domicilio de entrega hasta que registres uno nuevo.')) {
        router.delete(route('cliente.addresses.destroy'));
    }
};
</script>

<template>
    <Head title="Mi Domicilio - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :home-href="route('cliente.dashboard')"
                        :items="[
                            { label: 'Mi Domicilio', icon: 'fa-solid fa-house-chimney' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-sm shadow-xs shrink-0">
                            <i class="fa-solid fa-house-chimney text-sky-400 text-base"></i>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                                Mi Domicilio de Entrega
                            </h2>
                            <p class="text-xs text-slate-500">
                                Ubicación de tu casa para la entrega de tus garrafones en Hocabá
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="!address">
                    <Link
                        :href="route('cliente.addresses.create')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-blue-900 rounded-xl hover:bg-blue-800 transition shadow-xs cursor-pointer"
                    >
                        <i class="fa-solid fa-plus text-xs"></i>
                        <span>Registrar domicilio</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-7xl 2xl:max-w-screen-2xl px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Mensaje de Éxito Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-3 shadow-xs animate-fade-in"
                >
                    <div class="h-8 w-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-circle-check text-base"></i>
                    </div>
                    <span class="text-xs sm:text-sm font-medium">{{ $page.props.flash.success }}</span>
                </div>

                <!-- Mensaje de Advertencia Flash -->
                <div
                    v-if="$page.props.flash?.warning"
                    class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-950 flex items-center gap-3 shadow-xs animate-fade-in"
                >
                    <div class="h-8 w-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                    </div>
                    <span class="text-xs sm:text-sm font-medium">{{ $page.props.flash.warning }}</span>
                </div>

                <!-- Estado con Domicilio Registrado (Distribución Desktop 2 columnas) -->
                <div v-if="address" class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <!-- Columna Principal: Tarjeta Domicilio (8 cols) -->
                    <div class="lg:col-span-8 bg-white rounded-2xl p-6 sm:p-7 shadow-xs border border-slate-200/80 flex flex-col justify-between">
                        <div>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-11 w-11 rounded-2xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 border border-blue-100">
                                        <i class="fa-solid fa-location-dot text-lg text-blue-900"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900">
                                            Mi Casa
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            Domicilio único de entrega asociado a tu perfil
                                        </p>
                                    </div>
                                </div>

                                <div class="self-start sm:self-auto">
                                    <span
                                        v-if="address.is_hocaba && address.zone_key"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-blue-50 text-blue-900 border border-blue-200/80 shadow-2xs"
                                    >
                                        <i class="fa-solid fa-compass text-blue-700 text-xs"></i>
                                        <span>{{ address.zone_label }}</span>
                                    </span>
                                    <span
                                        v-else-if="!address.is_hocaba"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200 shadow-2xs"
                                    >
                                        <i class="fa-solid fa-city text-slate-500 text-xs"></i>
                                        <span>{{ address.municipality }}</span>
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs"
                                    >
                                        <i class="fa-solid fa-circle-question text-amber-600 text-xs"></i>
                                        <span>Sin zona asignada</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Datos del Domicilio -->
                            <div class="space-y-4">
                                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                        Calle y número
                                    </span>
                                    <p class="text-base sm:text-lg font-bold text-slate-900">
                                        {{ address.street_and_number }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                            Municipio
                                        </span>
                                        <p class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                                            <i class="fa-solid fa-map-pin text-sky-600"></i>
                                            {{ address.municipality }}, Yucatán
                                        </p>
                                    </div>

                                    <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                            Zona de reparto
                                        </span>
                                        <p class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                                            <i class="fa-solid fa-route text-blue-900"></i>
                                            {{ address.zone_label }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="address.reference" class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                        Referencias para el repartidor
                                    </span>
                                    <p class="text-xs sm:text-sm text-slate-700 leading-relaxed italic">
                                        "{{ address.reference }}"
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Acciones -->
                        <div class="flex items-center justify-between pt-5 mt-6 border-t border-slate-100">
                            <button
                                type="button"
                                @click="deleteAddress"
                                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 rounded-xl transition cursor-pointer"
                            >
                                <i class="fa-solid fa-trash-can text-xs"></i>
                                <span>Eliminar domicilio</span>
                            </button>

                            <Link
                                :href="route('cliente.addresses.edit')"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-xl transition shadow-xs cursor-pointer"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                <span>Editar datos</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Columna Lateral: Información y Guía (4 cols) -->
                    <div class="lg:col-span-4 space-y-5">
                        <!-- Guía de Reparto -->
                        <div class="bg-white rounded-2xl p-6 shadow-xs border border-slate-200/80">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="h-9 w-9 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-truck-fast text-sm"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-900">
                                    Rutas en Hocabá
                                </h4>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                El municipio de Hocabá se organiza en 4 zonas de entrega (Norte, Sur, Este y Oeste) para agilizar el reparto diario de garrafones en tu calle.
                            </p>
                        </div>

                        <!-- Tarjeta de Contacto Puritronic -->
                        <div class="rounded-2xl bg-gradient-to-br from-blue-950 to-slate-900 text-white p-6 shadow-xs">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="h-9 w-9 rounded-xl bg-white/10 text-sky-400 flex items-center justify-center shrink-0 border border-white/10">
                                    <i class="fa-solid fa-shield-halved text-sm"></i>
                                </div>
                                <h4 class="text-sm font-bold text-white">
                                    Garantía Puritronic
                                </h4>
                            </div>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Tus garrafones se transportan con sellos de garantía higiénica directamente hasta la puerta de tu casa.
                            </p>
                        </div>

                        <!-- Enlace Rápido al Dashboard -->
                        <Link
                            :href="route('cliente.dashboard')"
                            class="group block bg-white rounded-2xl p-4 shadow-xs border border-slate-200/80 hover:border-blue-900 transition-colors"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5 text-xs font-semibold text-slate-700 group-hover:text-blue-900 transition-colors">
                                    <i class="fa-solid fa-arrow-left text-slate-400 group-hover:text-blue-900"></i>
                                    <span>Volver al Inicio</span>
                                </div>
                                <i class="fa-solid fa-house text-slate-300 group-hover:text-blue-900 text-xs transition-colors"></i>
                            </div>
                        </Link>
                    </div>

                </div>

                <!-- Estado Vacío Sin Domicilio -->
                <div
                    v-else
                    class="bg-white rounded-2xl p-8 sm:p-14 text-center shadow-xs border border-slate-200/80 space-y-4 max-w-3xl mx-auto"
                >
                    <div class="h-16 w-16 mx-auto rounded-2xl bg-blue-50 text-blue-900 flex items-center justify-center border border-blue-100 shadow-2xs">
                        <i class="fa-solid fa-house-circle-exclamation text-3xl text-blue-900"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            Aún no has registrado tu domicilio
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto mt-1 leading-relaxed">
                            Para poder llevarte los garrafones de agua Puritronic, necesitamos registrar la dirección de tu casa en Hocabá.
                        </p>
                    </div>
                    <div class="pt-3">
                        <Link
                            :href="route('cliente.addresses.create')"
                            class="inline-flex items-center gap-2 px-6 py-2.5 text-xs font-bold text-white bg-blue-900 hover:bg-blue-800 rounded-xl transition shadow-xs cursor-pointer"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Registrar mi domicilio</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
