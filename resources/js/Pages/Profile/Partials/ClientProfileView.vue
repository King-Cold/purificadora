<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import UpdatePasswordForm from './UpdatePasswordForm.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
    clientData: {
        type: Object,
        required: true,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: props.clientData.customer.name || user.name || '',
    nickname: props.clientData.customer.nickname || '',
    phone: props.clientData.customer.phone || '',
    email: props.clientData.customer.email || user.email || '',
});

const formRecentlySuccessful = ref(false);

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            formRecentlySuccessful.value = true;
            setTimeout(() => {
                formRecentlySuccessful.value = false;
            }, 3500);
        },
    });
};
</script>

<template>
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

            <!-- DISTRIBUCIÓN DESKTOP EN 2 COLUMNAS (12 COLS: 7 Y 5) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- COLUMNA IZQUIERDA: DATOS PERSONALES Y SEGURIDAD (7 COLS) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- TARJETA 1: INFORMACIÓN PERSONAL -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                        <div class="p-5 sm:p-7 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 border border-blue-100">
                                    <i class="fa-solid fa-user-pen text-base"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Información Personal
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Administra tus datos de contacto y apodo comunitario
                                    </p>
                                </div>
                            </div>

                            <span class="text-xs text-slate-400 font-mono hidden sm:inline-block">
                                {{ clientData.customer.code }}
                            </span>
                        </div>

                        <form @submit.prevent="submit" class="p-5 sm:p-7 space-y-5">
                            <!-- Nombre Completo -->
                            <div>
                                <InputLabel for="name" value="Nombre completo *" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full text-sm"
                                    v-model="form.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Nombre y apellidos"
                                />
                                <InputError class="mt-1.5" :message="form.errors.name" />
                                <span class="text-xs text-slate-400 mt-1 block">
                                    Tu nombre particular registrado ante Puritronic.
                                </span>
                            </div>

                            <!-- Apodo Comunitario (Opcional) -->
                            <div class="p-4 rounded-xl bg-sky-50/50 border border-sky-100 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <InputLabel for="nickname" value="Apodo o alias comunitario (opcional)" class="text-sky-950 font-semibold" />
                                    <span class="text-[11px] text-sky-700 bg-sky-100 px-2 py-0.5 rounded-full font-medium">Opcional</span>
                                </div>
                                <TextInput
                                    id="nickname"
                                    type="text"
                                    class="mt-1 block w-full text-sm bg-white"
                                    v-model="form.nickname"
                                    placeholder="Ej. Don Pepe, Doña Mary, El Güero"
                                    maxlength="100"
                                />
                                <InputError class="mt-1.5" :message="form.errors.nickname" />
                                <span class="text-xs text-sky-800/80 mt-1 block leading-relaxed">
                                    En Hocabá muchas personas se identifican por su apodo o alias conocido. Puedes agregarlo, editarlo o dejarlo vacío cuando gustes.
                                </span>
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <InputLabel for="phone" value="Número de teléfono *" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full text-sm"
                                    v-model="form.phone"
                                    required
                                    autocomplete="tel"
                                    placeholder="Ej. 999 123 4567"
                                    maxlength="25"
                                />
                                <InputError class="mt-1.5" :message="form.errors.phone" />
                                <span class="text-xs text-slate-400 mt-1 block">
                                    Número de contacto utilizado por el repartidor para la entrega de tus garrafones.
                                </span>
                            </div>

                            <!-- Correo Electrónico -->
                            <div>
                                <InputLabel for="email" value="Correo electrónico *" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full text-sm"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                />
                                <InputError class="mt-1.5" :message="form.errors.email" />
                                <span class="text-xs text-slate-400 mt-1 block">
                                    Correo de acceso a tu cuenta en la purificadora.
                                </span>

                                <div v-if="mustVerifyEmail && user.email_verified_at === null" class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
                                    Tu dirección de correo electrónico aún no ha sido verificada.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="underline font-semibold hover:text-amber-900 ml-1 cursor-pointer"
                                    >
                                        Reenviar correo de verificación
                                    </Link>
                                    <div v-show="status === 'verification-link-sent'" class="mt-1 font-semibold text-emerald-700">
                                        Se ha enviado un nuevo enlace de verificación a tu correo.
                                    </div>
                                </div>
                            </div>

                            <!-- Acciones del Formulario -->
                            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                                <div>
                                    <span v-if="formRecentlySuccessful" class="text-xs font-semibold text-emerald-600 flex items-center gap-1.5 animate-fade-in">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <span>Información guardada</span>
                                    </span>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold text-white bg-blue-900 rounded-xl hover:bg-blue-800 transition shadow-xs disabled:opacity-50 cursor-pointer"
                                >
                                    <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
                                    <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                    <span>{{ form.processing ? 'Guardando...' : 'Guardar información' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TARJETA 4: SEGURIDAD DE LA CUENTA -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                        <div class="p-5 sm:p-7 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 border border-blue-100">
                                    <i class="fa-solid fa-shield-halved text-base"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Seguridad de la Cuenta
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Actualiza tu contraseña de acceso a la plataforma
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 sm:p-7 space-y-6">
                            <UpdatePasswordForm class="max-w-xl" />

                            <!-- Cierre de sesión -->
                            <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">Cierre de sesión</h4>
                                    <p class="text-xs text-slate-500">
                                        Finaliza tu sesión actual en este navegador de manera segura.
                                    </p>
                                </div>

                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer"
                                >
                                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                                    <span>Cerrar sesión</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- COLUMNA DERECHA: BOTELLONES Y DOMICILIO (5 COLS) -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- TARJETA 2: INFORMACIÓN COMO CLIENTE (MIS BOTELLONES) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center shrink-0 border border-blue-100">
                                    <i class="fa-solid fa-bottle-water text-base"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Mis Botellones
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Inventario registrado
                                    </p>
                                </div>
                            </div>

                            <span class="px-3 py-1 rounded-lg text-xs font-mono font-bold bg-blue-900 text-white shadow-2xs">
                                {{ clientData.customer.code }}
                            </span>
                        </div>

                        <div class="p-5 sm:p-6 space-y-4">
                            <!-- Fijos -->
                            <div class="p-4 rounded-xl bg-blue-50/60 border border-blue-200/80 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-semibold text-blue-900 uppercase tracking-wider block">Fijos (Con nombre)</span>
                                    <p class="text-xs text-blue-800 mt-1 max-w-[210px]">
                                        Identificados con tu nombre y apellido.
                                    </p>
                                </div>
                                <div class="text-3xl font-extrabold text-blue-950 font-mono">
                                    {{ clientData.customer.fixed_bottles_count }}
                                </div>
                            </div>

                            <!-- De Cambio -->
                            <div class="p-4 rounded-xl bg-sky-50/60 border border-sky-200/80 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-semibold text-sky-900 uppercase tracking-wider block">De Cambio</span>
                                    <p class="text-xs text-sky-800 mt-1 max-w-[210px]">
                                        Intercambiables comerciales.
                                    </p>
                                </div>
                                <div class="text-3xl font-extrabold text-sky-950 font-mono">
                                    {{ clientData.customer.exchange_bottles_count }}
                                </div>
                            </div>

                            <!-- Total -->
                            <div class="p-4 rounded-xl bg-slate-900 text-white flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-semibold text-sky-200 uppercase tracking-wider block">Total en posesión</span>
                                    <p class="text-xs text-slate-300 mt-1">
                                        Suma de todos tus envases.
                                    </p>
                                </div>
                                <div class="text-3xl font-extrabold text-white font-mono">
                                    {{ clientData.customer.total_bottles }}
                                </div>
                            </div>

                            <!-- Nota informativa -->
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-xs flex items-start gap-2.5">
                                <i class="fa-solid fa-circle-info text-blue-900 mt-0.5 shrink-0 text-sm"></i>
                                <span class="leading-relaxed">
                                    El control físico de envases es gestionado directamente por el personal de reparto de Puritronic.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- TARJETA 3: MI DOMICILIO DE ENTREGA -->
                    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                        <div class="p-5 sm:p-6 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-800 flex items-center justify-center shrink-0 border border-emerald-100">
                                    <i class="fa-solid fa-house-chimney text-base"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900">
                                        Domicilio de Casa
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Entrega en Hocabá
                                    </p>
                                </div>
                            </div>

                            <Link
                                v-if="clientData.address.has_address"
                                :href="route('cliente.addresses.edit')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-900 bg-blue-50 hover:bg-blue-100 rounded-lg transition border border-blue-200 cursor-pointer"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                <span>Editar</span>
                            </Link>
                        </div>

                        <!-- Estado con Domicilio Registrado -->
                        <div v-if="clientData.address.has_address" class="p-5 sm:p-6 space-y-3.5">
                            <div class="p-3.5 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                    Calle y número
                                </span>
                                <p class="text-base font-bold text-slate-900">
                                    {{ clientData.address.street_and_number }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-0.5">
                                        Municipio
                                    </span>
                                    <p class="text-xs font-semibold text-slate-800">
                                        {{ clientData.address.municipality }}
                                    </p>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-0.5">
                                        Zona
                                    </span>
                                    <span class="text-xs font-bold text-blue-900">
                                        {{ clientData.address.zone_label }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="clientData.address.reference" class="p-3 rounded-xl bg-slate-50/70 border border-slate-200/60">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-0.5">
                                    Referencias
                                </span>
                                <p class="text-xs text-slate-700 italic">
                                    "{{ clientData.address.reference }}"
                                </p>
                            </div>
                        </div>

                        <!-- Estado Vacío Sin Domicilio -->
                        <div v-else class="p-6 text-center space-y-3">
                            <div class="h-12 w-12 mx-auto rounded-xl bg-blue-50 text-blue-900 flex items-center justify-center border border-blue-100">
                                <i class="fa-solid fa-house-circle-exclamation text-xl text-blue-900"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">
                                    Sin domicilio registrado
                                </h4>
                                <p class="text-xs text-slate-500 max-w-xs mx-auto mt-0.5">
                                    Indica la dirección de tu casa para recibir garrafones de agua.
                                </p>
                            </div>
                            <div class="pt-1">
                                <Link
                                    :href="route('cliente.addresses.create')"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-lg transition shadow-xs cursor-pointer"
                                >
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Registrar mi domicilio</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</template>
