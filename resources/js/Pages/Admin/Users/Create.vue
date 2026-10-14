<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    assignableRoles: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    email: '',
    role: props.assignableRoles[0] || 'Administrador',
    password: '',
    password_confirmation: '',
});

const roleDescriptions = {
    'Administrador': 'Gestión operativa, comercial, de pedidos, finanzas e inventario.',
    'Cliente': 'Acceso al portal de autoservicio para consulta de pedidos y domicilios.',
    'Repartidor': 'Consulta de pedidos en ruta, registro de entregas y control de garrafones.',
    'Operador Planta': 'Monitoreo de llenado, sellado y control de stock de insumos en planta.',
};

const submit = () => {
    form.post(route('admin.users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Crear Usuario - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('admin.users.index')" class="hover:text-blue-900 transition">
                            Usuarios
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">Nuevo</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Registrar Nuevo Usuario
                    </h2>
                    <p class="text-sm text-gray-500">
                        Crea una cuenta de acceso y asigna los permisos operativos correspondientes
                    </p>
                </div>

                <Link
                    :href="route('admin.users.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition self-start sm:self-auto shadow-sm"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver al listado</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- Encabezado de sección -->
                        <div class="border-b border-gray-100 pb-4">
                            <h3 class="text-base font-semibold text-gray-900">
                                Información de la Cuenta
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Todos los campos marcados con asterisco (*) son obligatorios
                            </p>
                        </div>

                        <!-- Nombre Completo -->
                        <div>
                            <InputLabel for="name" value="Nombre completo *" class="font-medium text-gray-700" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                v-model="form.name"
                                required
                                autofocus
                                placeholder="Ej. Juan Pérez González"
                                autocomplete="name"
                            />
                            <InputError class="mt-1.5" :message="form.errors.name" />
                        </div>

                        <!-- Correo Electrónico -->
                        <div>
                            <InputLabel for="email" value="Correo electrónico *" class="font-medium text-gray-700" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                v-model="form.email"
                                required
                                placeholder="usuario@puritronic.com"
                                autocomplete="username"
                            />
                            <InputError class="mt-1.5" :message="form.errors.email" />
                            <p class="text-[11px] text-gray-400 mt-1">
                                Se utilizará como identificador de inicio de sesión.
                            </p>
                        </div>

                        <!-- Selector de Rol -->
                        <div>
                            <InputLabel for="role" value="Rol de acceso asignado *" class="font-medium text-gray-700" />
                            <div class="mt-1.5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    v-for="r in assignableRoles"
                                    :key="r"
                                    class="relative flex flex-col p-3.5 border rounded-lg cursor-pointer transition focus:outline-none"
                                    :class="form.role === r ? 'border-blue-900 bg-blue-50/50 ring-1 ring-blue-900' : 'border-gray-200 hover:border-gray-300 bg-white'"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-semibold text-gray-900">{{ r }}</span>
                                        <input
                                            type="radio"
                                            name="role"
                                            :value="r"
                                            v-model="form.role"
                                            class="h-4 w-4 text-blue-900 border-gray-300 focus:ring-blue-900"
                                        />
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                        {{ roleDescriptions[r] || 'Acceso al sistema.' }}
                                    </p>
                                    <span v-if="r === 'Cliente'" class="mt-2 inline-flex items-center text-[11px] font-medium text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded w-fit">
                                        Se creará automáticamente su perfil Customer
                                    </span>
                                </label>
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.role" />
                        </div>

                        <!-- Contraseñas -->
                        <div class="border-t border-gray-100 pt-5 space-y-4">
                            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                Credenciales de Acceso
                            </h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Password -->
                                <div>
                                    <InputLabel for="password" value="Contraseña *" class="font-medium text-gray-700" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.password"
                                        required
                                        placeholder="Mínimo 8 caracteres"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password" />
                                </div>

                                <!-- Password Confirmation -->
                                <div>
                                    <InputLabel for="password_confirmation" value="Confirmar contraseña *" class="font-medium text-gray-700" />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.password_confirmation"
                                        required
                                        placeholder="Repite la contraseña"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                            <Link
                                :href="route('admin.users.index')"
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
                                <span>{{ form.processing ? 'Registrando...' : 'Guardar usuario' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
