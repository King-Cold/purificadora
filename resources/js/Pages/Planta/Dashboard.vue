<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Panel de Planta - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Panel de Planta y Llenado
                    </h2>
                    <p class="text-sm text-gray-500">Módulo de producción y control de calidad</p>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 self-start sm:self-auto">
                    Rol: Operador Planta
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-xl border border-gray-100">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-14 w-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-2xl shadow-inner">
                                ⚙️
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    Bienvenido, {{ $page.props.auth.user.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Acceso concedido al área de llenado, sellado y almacén de producto terminado.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 my-6">
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Operador a Cargo</span>
                                <p class="text-base font-medium text-gray-800 mt-1">{{ $page.props.auth.user.name }}</p>
                            </div>
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Línea de Purificación</span>
                                <p class="text-base font-medium text-emerald-600 mt-1">Operativa y calibrada</p>
                            </div>
                        </div>

                        <div class="rounded-lg bg-emerald-50/60 p-4 border border-emerald-100 text-sm text-emerald-900 mb-8">
                            <p class="font-medium">Control de Llenado e Inventario</p>
                            <p class="text-xs text-emerald-700 mt-1">
                                Próximamente podrás registrar lotes de llenado de garrafones, consumo de insumos (tapas, sellos, garrafones nuevos) y monitoreo de stock disponible para rutas.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-100">
                            <span class="text-xs text-gray-400">Puritronic &copy; Planta Purificadora</span>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-lg font-medium text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition duration-150"
                            >
                                Cerrar sesión
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
