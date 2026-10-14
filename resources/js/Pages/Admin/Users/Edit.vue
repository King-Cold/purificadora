<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    assignableRoles: {
        type: Array,
        required: true,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
    canChangeRole: {
        type: Boolean,
        default: true,
    },
    roleLockReason: {
        type: String,
        default: null,
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role: props.user.role,
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
    form.put(route('admin.users.update', props.user.id), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="`Editar Usuario: ${user.name} - Puritronic`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('admin.users.index')" class="hover:text-blue-900 transition">
                            Usuarios
                        </Link>
                        <span>/</span>
                        <Link :href="route('admin.users.show', user.id)" class="hover:text-blue-900 transition">
                            {{ user.name }}
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">Editar</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Modificar Usuario
                    </h2>
                    <p class="text-sm text-gray-500">
                        Actualiza la información general, asignación de rol o credenciales de acceso
                    </p>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <Link
                        :href="route('admin.users.show', user.id)"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm"
                    >
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Cancelar</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- Encabezado de sección -->
                        <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900">
                                    Datos de la Cuenta #{{ user.id }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Modifique los campos requeridos respetando las restricciones de seguridad
                                </p>
                            </div>
                            <span
                                v-if="user.has_customer"
                                class="text-xs px-2.5 py-1 rounded bg-sky-50 text-sky-800 border border-sky-200 font-mono font-medium"
                            >
                                Perfil Cliente: {{ user.customer_code }}
                            </span>
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
                            <p v-if="user.has_customer" class="text-[11px] text-gray-500 mt-1">
                                Nota: El nombre en el perfil comercial vinculado (Customer) se actualizará automáticamente para mantener la consistencia.
                            </p>
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
                                Se validará que no esté duplicado con otra cuenta activa.
                            </p>
                        </div>

                        <!-- Selector de Rol de Acceso -->
                        <div class="border-t border-gray-100 pt-5">
                            <div class="flex items-center justify-between mb-1.5">
                                <InputLabel for="role" value="Rol de acceso asignado *" class="font-medium text-gray-700" />
                                <span v-if="!canChangeRole" class="text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                    Modificación bloqueada
                                </span>
                            </div>

                            <!-- Advertencia si el rol está bloqueado (por ser el propio usuario o por perfil Customer) -->
                            <div v-if="roleLockReason" class="mb-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-start gap-2">
                                <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ roleLockReason }}</span>
                            </div>

                            <div v-if="canChangeRole" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                                </label>
                            </div>

                            <!-- Rol estático cuando no se puede cambiar -->
                            <div v-else class="p-3.5 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-semibold text-gray-800">{{ form.role }}</span>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ roleDescriptions[form.role] || 'Rol asignado al usuario.' }}
                                    </p>
                                </div>
                                <span class="text-xs font-mono px-2 py-1 bg-gray-200/70 text-gray-700 rounded">
                                    Fijo
                                </span>
                            </div>

                            <InputError class="mt-1.5" :message="form.errors.role" />
                        </div>

                        <!-- Sección de Cambio de Contraseña (Opcional) -->
                        <div class="border-t border-gray-100 pt-5 space-y-4">
                            <div>
                                <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    Actualizar Contraseña (Opcional)
                                </h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Deje ambos campos vacíos si desea conservar la contraseña actual del usuario.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Nueva Contraseña -->
                                <div>
                                    <InputLabel for="password" value="Nueva contraseña" class="font-medium text-gray-700" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.password"
                                        placeholder="Solo si desea cambiarla"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password" />
                                </div>

                                <!-- Confirmar Contraseña -->
                                <div>
                                    <InputLabel for="password_confirmation" value="Confirmar nueva contraseña" class="font-medium text-gray-700" />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-lg border-gray-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.password_confirmation"
                                        placeholder="Repetir nueva contraseña"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-100">
                            <Link
                                :href="route('admin.users.show', user.id)"
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
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
