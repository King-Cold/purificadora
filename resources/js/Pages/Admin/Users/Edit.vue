<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    isSelf: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
});

const getRoleBadge = (role) => {
    switch (role) {
        case 'Super Admin':
            return { bg: 'bg-purple-100 text-purple-900 border-purple-300', icon: 'fa-user-shield' };
        case 'Administrador':
            return { bg: 'bg-blue-100 text-blue-900 border-blue-300', icon: 'fa-shield-halved' };
        case 'Repartidor':
            return { bg: 'bg-amber-100 text-amber-900 border-amber-300', icon: 'fa-truck' };
        case 'Operador Planta':
            return { bg: 'bg-emerald-100 text-emerald-900 border-emerald-300', icon: 'fa-industry' };
        case 'Cliente':
            return { bg: 'bg-sky-100 text-sky-900 border-sky-300', icon: 'fa-user' };
        default:
            return { bg: 'bg-slate-100 text-slate-800 border-slate-300', icon: 'fa-user' };
    }
};

const getInitials = (name) => {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
};

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head :title="`Editar Usuario: ${user.name} - Puritronic`" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Usuarios', href: route('admin.users.index'), icon: 'fa-solid fa-users' },
                            { label: user.name, href: route('admin.users.show', user.id), icon: 'fa-solid fa-user' },
                            { label: 'Editar', icon: 'fa-solid fa-user-pen' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                            {{ getInitials(user.name) }}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold leading-tight text-slate-900">
                                Modificar Cuenta: {{ user.name }}
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-500">
                                Actualice las credenciales y datos permitidos de la cuenta
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <Link
                        :href="route('admin.users.show', user.id)"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition shadow-xs"
                    >
                        <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                        <span>Cancelar</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 border-t-4 border-t-blue-900 overflow-hidden">
                    
                    <!-- Encabezado de la Tarjeta con datos informativos -->
                    <div class="bg-slate-50 border-b border-slate-200 px-6 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-900 flex items-center justify-center font-bold text-sm shrink-0">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                    Cuenta #{{ user.id }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Los roles del personal son fijos y permanentes
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- Badge de Rol Permanente -->
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border font-semibold text-xs shadow-2xs"
                                :class="getRoleBadge(user.role).bg"
                            >
                                <i :class="`fa-solid ${getRoleBadge(user.role).icon} text-xs`"></i>
                                Rol: {{ user.role }}
                            </span>

                            <span
                                v-if="user.has_customer"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-sky-100 text-sky-900 border border-sky-300 font-mono font-bold text-xs shadow-2xs"
                            >
                                <i class="fa-solid fa-address-book text-sky-700"></i>
                                {{ user.customer_code }}
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- Sección de Información de Cuenta -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200">
                                <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                    Datos de Identificación y Acceso
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- Nombre o Puesto de Trabajo -->
                                <div>
                                    <InputLabel for="name" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        {{ user.is_position_fixed ? 'Puesto de trabajo' : 'Nombre completo' }}
                                    </InputLabel>

                                    <!-- Campo bloqueado para cuentas fijas (Repartidor / Llenador) -->
                                    <div v-if="user.is_position_fixed" class="mt-1.5">
                                        <input
                                            type="text"
                                            :value="user.name"
                                            disabled
                                            class="block w-full text-sm rounded-xl border-slate-200 bg-slate-100 text-slate-700 font-bold cursor-not-allowed py-2 px-3"
                                        />
                                        <p class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                                            <i class="fa-solid fa-lock text-[10px] text-slate-400"></i>
                                            <span>Cuenta identificada únicamente por su puesto de trabajo.</span>
                                        </p>
                                    </div>

                                    <!-- Campo editable para Administrador o Cliente -->
                                    <div v-else>
                                        <TextInput
                                            id="name"
                                            type="text"
                                            class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                            v-model="form.name"
                                            required
                                            autofocus
                                            placeholder="Nombre del usuario"
                                            autocomplete="name"
                                        />
                                        <InputError class="mt-1.5" :message="form.errors.name" />
                                        <p v-if="user.has_customer" class="text-[11px] text-blue-700 mt-1">
                                            <i class="fa-solid fa-link text-[10px] mr-1"></i>
                                            Se sincroniza con el perfil de cliente.
                                        </p>
                                    </div>
                                </div>

                                <!-- Correo Electrónico (Login) -->
                                <div>
                                    <InputLabel for="email" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        Correo electrónico de acceso <span class="text-rose-500">*</span>
                                    </InputLabel>
                                    <TextInput
                                        id="email"
                                        type="email"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.email"
                                        required
                                        placeholder="usuario@puritronic.com"
                                        autocomplete="username"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.email" />
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        Utilizado para el inicio de sesión en Puritronic.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Actualización de Contraseña -->
                        <div class="space-y-4 pt-4 border-t border-slate-200">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-key"></i>
                                    </div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                        Cambiar Contraseña
                                    </h4>
                                </div>
                                <span class="text-[11px] font-semibold text-emerald-800 bg-emerald-100 px-2.5 py-0.5 rounded-full">
                                    Opcional
                                </span>
                            </div>

                            <p class="text-xs text-slate-500">
                                Deje ambos campos vacíos para conservar la contraseña actual de esta cuenta.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <!-- Password -->
                                <div>
                                    <InputLabel for="password" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        Nueva contraseña
                                    </InputLabel>
                                    <TextInput
                                        id="password"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-emerald-600 focus:ring-emerald-600"
                                        v-model="form.password"
                                        placeholder="Solo si desea cambiarla"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password" />
                                </div>

                                <!-- Password Confirmation -->
                                <div>
                                    <InputLabel for="password_confirmation" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        Confirmar nueva contraseña
                                    </InputLabel>
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-emerald-600 focus:ring-emerald-600"
                                        v-model="form.password_confirmation"
                                        placeholder="Repetir nueva contraseña"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-6 border-t border-slate-200">
                            <Link
                                :href="route('admin.users.show', user.id)"
                                class="w-full sm:w-auto text-center px-5 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition shadow-xs"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-900 hover:bg-blue-800 active:bg-blue-950 border border-transparent rounded-xl font-bold text-sm text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
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
                                <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>{{ form.processing ? 'Guardando cambios...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
