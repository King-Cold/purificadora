<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AdminPageHeader from '@/Components/AdminPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

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

const initialBottleType = Number(props.user.customer?.fixed_bottles_count || 0) > 0 ? 'fixed' : 'exchange';
const selectedBottleType = ref(initialBottleType);
const selectedBottleQty = ref(
    initialBottleType === 'fixed'
        ? Number(props.user.customer?.fixed_bottles_count || 0)
        : Number(props.user.customer?.exchange_bottles_count || 0)
);

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    bottle_type: selectedBottleType.value,
    bottles_count: selectedBottleQty.value,
    fixed_bottles_count: props.user.customer?.fixed_bottles_count ?? 0,
    exchange_bottles_count: props.user.customer?.exchange_bottles_count ?? 0,
});

const syncBottleFields = () => {
    const qty = Math.min(500, Math.max(0, parseInt(selectedBottleQty.value, 10) || 0));
    selectedBottleQty.value = qty;
    form.bottle_type = selectedBottleType.value;
    form.bottles_count = qty;
    if (selectedBottleType.value === 'fixed') {
        form.fixed_bottles_count = qty;
        form.exchange_bottles_count = 0;
    } else {
        form.fixed_bottles_count = 0;
        form.exchange_bottles_count = qty;
    }
};

const totalBottles = computed(() => {
    return Math.max(0, parseInt(selectedBottleQty.value, 10) || 0);
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
    <Head :title="`Editar · ${user.name}`" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <AdminPageHeader
                :title="`Modificar Cuenta: ${user.name}`"
                subtitle="Actualice las credenciales y datos permitidos de la cuenta."
                icon="fa-solid fa-user-pen"
                accent="purple"
                :breadcrumbs="[
                    { label: 'Administración', href: route('dashboard') },
                    { label: 'Usuarios', href: route('admin.users.index') },
                    { label: user.name, href: route('admin.users.show', user.id) },
                    { label: 'Editar' },
                ]"
            >
                <template #icon>
                    <span class="font-bold text-sm tracking-tight">{{ getInitials(user.name) }}</span>
                </template>

                <template #actions>
                    <Link
                        :href="route('admin.users.show', user.id)"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-medium text-[#777386] hover:text-[#211B35] bg-white hover:bg-[#F5F4F8] border border-[#E7E4ED] rounded-xl transition duration-150 shadow-2xs"
                    >
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Cancelar</span>
                    </Link>
                </template>
            </AdminPageHeader>
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

                        <!-- SECCIÓN: BOTELLONES DEL CLIENTE (SI ES CUENTA DE CLIENTE) -->
                        <div v-if="user.has_customer" class="space-y-4 pt-4 border-t border-slate-200">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-2 border-b border-slate-200 gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-bottle-water"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                            Cantidad de Botellones (20 Litros)
                                        </h4>
                                        <p class="text-[11px] text-slate-500 font-normal">
                                            Configura los botellones que esta persona tiene registrados para pedidos a domicilio.
                                        </p>
                                    </div>
                                </div>

                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-blue-900 text-white text-xs font-bold self-start sm:self-auto">
                                    <span>Total:</span>
                                    <span class="text-sm font-mono font-bold">{{ totalBottles }}</span>
                                    <span>garrafones de 20L</span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        @click="selectedBottleType = 'exchange'; syncBottleFields()"
                                        class="p-3.5 rounded-xl border text-left transition cursor-pointer"
                                        :class="selectedBottleType === 'exchange'
                                            ? 'bg-sky-50 border-sky-400 ring-2 ring-sky-200 text-slate-900'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    >
                                        <span class="block text-xs font-bold">Botellones de cambio</span>
                                        <span class="block text-[11px] text-slate-500">Intercambiables</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="selectedBottleType = 'fixed'; syncBottleFields()"
                                        class="p-3.5 rounded-xl border text-left transition cursor-pointer"
                                        :class="selectedBottleType === 'fixed'
                                            ? 'bg-blue-50 border-blue-400 ring-2 ring-blue-200 text-slate-900'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                                    >
                                        <span class="block text-xs font-bold">Botellones fijos</span>
                                        <span class="block text-[11px] text-slate-500">Rotulados</span>
                                    </button>
                                </div>
                                <InputError class="mt-1.5" :message="form.errors.bottle_type" />

                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
                                    <InputLabel for="bottles_count" class="font-bold text-slate-900 text-xs mb-1.5">
                                        Cantidad máxima de botellones ({{ selectedBottleType === 'fixed' ? 'Fijos' : 'De cambio' }})
                                    </InputLabel>
                                    <TextInput
                                        id="bottles_count"
                                        type="number"
                                        min="0"
                                        max="500"
                                        step="1"
                                        class="block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 font-semibold"
                                        v-model.number="selectedBottleQty"
                                        @input="syncBottleFields"
                                        required
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.bottles_count || form.errors.fixed_bottles_count || form.errors.exchange_bottles_count" />
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
