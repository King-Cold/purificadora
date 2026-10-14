<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Panel de Cliente - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        Portal de Cliente
                    </h2>
                    <p class="text-sm text-gray-500">Bienvenido a tu cuenta en Puritronic</p>
                </div>
                <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 self-start sm:self-auto">
                    Rol: Cliente
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-xl border border-gray-100">
                    <div class="p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="h-14 w-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-2xl shadow-inner">
                                💧
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    Hola, {{ $page.props.auth.user.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Tu perfil de cliente se encuentra activo y vinculado correctamente.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 my-6">
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Correo registrado</span>
                                <p class="text-base font-medium text-gray-800 mt-1">{{ $page.props.auth.user.email }}</p>
                            </div>
                            <div class="p-4 rounded-lg bg-gray-50 border border-gray-100">
                                <span class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Tipo de cuenta</span>
                                <p class="text-base font-medium text-blue-600 mt-1">Cliente Particular / Comercial</p>
                            </div>
                        </div>

                        <div class="rounded-lg bg-blue-50/60 p-4 border border-blue-100 text-sm text-blue-800 mb-8">
                            <p class="font-medium">Servicio Puritronic</p>
                            <p class="text-xs text-blue-700 mt-1">
                                Próximamente podrás realizar solicitudes de pedidos en línea, consultar tu saldo de garrafones y rastrear tus entregas en tiempo real.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-100">
                            <span class="text-xs text-gray-400">Puritronic &copy; Agua Purificada de Calidad</span>
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
