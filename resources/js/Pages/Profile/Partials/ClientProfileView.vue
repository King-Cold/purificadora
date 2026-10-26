<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import UpdatePasswordForm from './UpdatePasswordForm.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
const activeTab = ref('personal'); // 'personal' | 'security'

// Formulario de Datos Personales
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

// Edición de Botellones
const isEditingBottles = ref(false);
const bottleRecentlySuccessful = ref(false);
const bottleForm = useForm({
    fixed_bottles_count: props.clientData.customer.fixed_bottles_count ?? 0,
    exchange_bottles_count: props.clientData.customer.exchange_bottles_count ?? 0,
});

watch(
    () => props.clientData.customer,
    (newCust) => {
        if (newCust && !isEditingBottles.value) {
            bottleForm.fixed_bottles_count = newCust.fixed_bottles_count ?? 0;
            bottleForm.exchange_bottles_count = newCust.exchange_bottles_count ?? 0;
        }
    },
    { deep: true }
);

const computedEditingTotal = computed(() => {
    const fixed = Number.isFinite(Number(bottleForm.fixed_bottles_count))
        ? Math.max(0, parseInt(bottleForm.fixed_bottles_count, 10))
        : 0;
    const exchange = Number.isFinite(Number(bottleForm.exchange_bottles_count))
        ? Math.max(0, parseInt(bottleForm.exchange_bottles_count, 10))
        : 0;
    return fixed + exchange;
});

const openEditBottles = () => {
    bottleForm.fixed_bottles_count = props.clientData.customer.fixed_bottles_count ?? 0;
    bottleForm.exchange_bottles_count = props.clientData.customer.exchange_bottles_count ?? 0;
    bottleForm.clearErrors();
    isEditingBottles.value = true;
};

const cancelEditBottles = () => {
    bottleForm.clearErrors();
    isEditingBottles.value = false;
};

const submitBottles = () => {
    bottleForm.put(route('cliente.bottles.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isEditingBottles.value = false;
            bottleRecentlySuccessful.value = true;
            setTimeout(() => {
                bottleRecentlySuccessful.value = false;
            }, 3500);
        },
    });
};

// Formato limpio del Domicilio usando campos reales
const addressDisplay = computed(() => {
    const addr = props.clientData.address;
    if (!addr || !addr.has_address) return null;

    const street = addr.street?.trim() || addr.street_and_number?.trim() || '';
    const ext = addr.exterior_number?.trim();
    const interior = addr.interior_number?.trim();

    let streetPart = street;
    if (ext && ext !== 'S/N' && !street.toLowerCase().includes(ext.toLowerCase())) {
        streetPart += ` #${ext}`;
    } else if (ext === 'S/N' && !street.toLowerCase().includes('s/n') && !street.toLowerCase().includes('sin número')) {
        streetPart += ' S/N';
    }
    if (interior && !street.toLowerCase().includes(interior.toLowerCase())) {
        streetPart += `, Int. ${interior}`;
    }

    const lines = [];
    if (streetPart) {
        lines.push(streetPart);
    }

    if (addr.between_streets?.trim()) {
        lines.push(`Cruzamientos: ${addr.between_streets.trim()}`);
    }

    const locationParts = [];
    if (addr.neighborhood?.trim()) {
        const neigh = addr.neighborhood.trim();
        locationParts.push(
            neigh.toLowerCase().startsWith('col.') || neigh.toLowerCase().startsWith('colonia')
                ? neigh
                : `Col. ${neigh}`
        );
    }

    const city = addr.city?.trim() || addr.municipality?.trim() || 'Hocabá';
    locationParts.push(city);

    const state = addr.state?.trim() || 'Yucatán';
    if (!city.toLowerCase().includes(state.toLowerCase())) {
        locationParts.push(state);
    }

    if (addr.postal_code?.trim()) {
        locationParts.push(`C.P. ${addr.postal_code.trim()}`);
    }

    if (locationParts.length > 0) {
        lines.push(locationParts.join(', '));
    }

    return {
        lines,
        reference: addr.reference?.trim() || null,
        zoneLabel: addr.zone_label || 'Sin zona asignada',
    };
});
</script>

<template>
    <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Mensaje de Éxito Flash -->
            <div
                v-if="$page.props.flash?.success"
                class="p-3.5 sm:p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-2.5 text-xs sm:text-sm"
            >
                <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
                <span>{{ $page.props.flash.success }}</span>
            </div>

            <!-- Navegación de Pestañas: "Datos personales" y "Seguridad" -->
            <div class="border-b border-slate-200/80">
                <nav class="-mb-px flex space-x-6" aria-label="Pestañas del perfil">
                    <button
                        type="button"
                        @click="activeTab = 'personal'"
                        :class="[
                            activeTab === 'personal'
                                ? 'border-blue-900 text-blue-900 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium',
                            'whitespace-nowrap py-3 px-1 border-b-2 text-sm transition-colors cursor-pointer focus:outline-none'
                        ]"
                    >
                        <i class="fa-solid fa-user text-xs mr-2"></i>
                        Datos personales
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'security'"
                        :class="[
                            activeTab === 'security'
                                ? 'border-blue-900 text-blue-900 font-semibold'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium',
                            'whitespace-nowrap py-3 px-1 border-b-2 text-sm transition-colors cursor-pointer focus:outline-none'
                        ]"
                    >
                        <i class="fa-solid fa-shield-halved text-xs mr-2"></i>
                        Seguridad
                    </button>
                </nav>
            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 1: DATOS PERSONALES                -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'personal'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Columna Izquierda: Formulario de Datos Personales (7 cols) -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-5">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900">
                            Información personal
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Actualiza tus datos de contacto para la entrega de pedidos
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
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
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <!-- Apodo opcional (sin recuadro especial, con ayuda breve) -->
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="nickname" value="Apodo (opcional)" />
                                <span class="text-[11px] text-slate-400">Opcional</span>
                            </div>
                            <TextInput
                                id="nickname"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                v-model="form.nickname"
                                placeholder="Ej. Don Pepe"
                                maxlength="100"
                            />
                            <span class="text-xs text-slate-400 mt-1 block">
                                Así podemos identificarte al entregar.
                            </span>
                            <InputError class="mt-1" :message="form.errors.nickname" />
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
                            <InputError class="mt-1" :message="form.errors.phone" />
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
                            <InputError class="mt-1" :message="form.errors.email" />

                            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800">
                                Tu dirección de correo aún no ha sido verificada.
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

                        <!-- Botón Principal "Guardar cambios" -->
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                            <div>
                                <span v-if="formRecentlySuccessful" class="text-xs font-semibold text-emerald-600 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                    <span>Cambios guardados</span>
                                </span>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-blue-900 rounded-xl hover:bg-blue-800 transition shadow-xs disabled:opacity-50 cursor-pointer min-h-[44px] focus:outline-none focus:ring-2 focus:ring-blue-900/30"
                            >
                                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Columna Derecha: Resúmenes de Botellones y Domicilio (5 cols) -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- Resumen 1: Botellones (Una sola fila: Fijos, De cambio y Total con "Envases de 20 litros" una vez) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">
                                    Mis botellones
                                </h3>
                                <p class="text-xs text-slate-500">
                                    Envases de 20 litros
                                </p>
                            </div>

                            <button
                                v-if="!isEditingBottles"
                                type="button"
                                @click="openEditBottles"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-blue-900 bg-blue-50 hover:bg-blue-100 rounded-lg transition border border-blue-200/80 cursor-pointer"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                <span>Modificar</span>
                            </button>
                        </div>

                        <!-- Feedback de guardado exitoso de botellones -->
                        <div v-if="bottleRecentlySuccessful" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i>
                            <span class="font-medium">Botellones actualizados correctamente.</span>
                        </div>

                        <!-- MODO EDICIÓN BOTELLONES -->
                        <form v-if="isEditingBottles" @submit.prevent="submitBottles" class="space-y-3.5 pt-1">
                            <!-- Fijos -->
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-semibold text-slate-800 block">Fijos</span>
                                    <span class="text-[11px] text-slate-500">Con tu nombre</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="bottleForm.fixed_bottles_count = Math.max(0, (parseInt(bottleForm.fixed_bottles_count) || 0) - 1)"
                                        class="h-9 w-9 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Restar fijo"
                                    >
                                        <i class="fa-solid fa-minus text-xs"></i>
                                    </button>
                                    <input
                                        type="number"
                                        min="0"
                                        max="500"
                                        v-model.number="bottleForm.fixed_bottles_count"
                                        class="w-14 text-center font-mono font-bold text-sm rounded-lg border border-slate-200 py-1 bg-white text-slate-900"
                                    />
                                    <button
                                        type="button"
                                        @click="bottleForm.fixed_bottles_count = Math.min(500, (parseInt(bottleForm.fixed_bottles_count) || 0) + 1)"
                                        class="h-9 w-9 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Sumar fijo"
                                    >
                                        <i class="fa-solid fa-plus text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <InputError :message="bottleForm.errors.fixed_bottles_count" />

                            <!-- De cambio -->
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-semibold text-slate-800 block">De cambio</span>
                                    <span class="text-[11px] text-slate-500">Comerciales estándar</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="bottleForm.exchange_bottles_count = Math.max(0, (parseInt(bottleForm.exchange_bottles_count) || 0) - 1)"
                                        class="h-9 w-9 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Restar de cambio"
                                    >
                                        <i class="fa-solid fa-minus text-xs"></i>
                                    </button>
                                    <input
                                        type="number"
                                        min="0"
                                        max="500"
                                        v-model.number="bottleForm.exchange_bottles_count"
                                        class="w-14 text-center font-mono font-bold text-sm rounded-lg border border-slate-200 py-1 bg-white text-slate-900"
                                    />
                                    <button
                                        type="button"
                                        @click="bottleForm.exchange_bottles_count = Math.min(500, (parseInt(bottleForm.exchange_bottles_count) || 0) + 1)"
                                        class="h-9 w-9 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Sumar de cambio"
                                    >
                                        <i class="fa-solid fa-plus text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <InputError :message="bottleForm.errors.exchange_bottles_count" />

                            <!-- Total calculado -->
                            <div class="p-3 rounded-xl bg-blue-50/80 border border-blue-100 text-blue-950 flex items-center justify-between text-xs font-semibold">
                                <span>Total (20L)</span>
                                <span class="text-base font-mono font-bold">{{ computedEditingTotal }}</span>
                            </div>

                            <!-- Botones Cancelar / Guardar -->
                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <button
                                    type="button"
                                    @click="cancelEditBottles"
                                    class="px-3.5 py-2 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="bottleForm.processing"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-xl transition shadow-xs disabled:opacity-50 cursor-pointer min-h-[38px]"
                                >
                                    <i v-if="bottleForm.processing" class="fa-solid fa-spinner fa-spin text-xs"></i>
                                    <span>{{ bottleForm.processing ? 'Guardando...' : 'Guardar botellones' }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- MODO VISTA: UNA SOLA FILA CON FIJOS, DE CAMBIO Y TOTAL -->
                        <div v-else class="grid grid-cols-3 gap-2.5 text-center">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/60">
                                <span class="text-xs font-medium text-slate-500 block">Fijos</span>
                                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 mt-1 block">
                                    {{ clientData.customer.fixed_bottles_count }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/60">
                                <span class="text-xs font-medium text-slate-500 block">De cambio</span>
                                <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 mt-1 block">
                                    {{ clientData.customer.exchange_bottles_count }}
                                </span>
                            </div>

                            <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-100 text-blue-950">
                                <span class="text-xs font-semibold text-blue-900 block">Total</span>
                                <span class="text-xl sm:text-2xl font-extrabold font-mono text-blue-950 mt-1 block">
                                    {{ clientData.customer.total_bottles }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen 2: Domicilio de entrega (Dirección completa, zona y enlace "Editar domicilio" con datos reales) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-900">
                                Domicilio de entrega
                            </h3>

                            <span
                                v-if="clientData.address.has_address && addressDisplay"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80 shrink-0"
                            >
                                {{ addressDisplay.zoneLabel }}
                            </span>
                        </div>

                        <!-- Con Domicilio Registrado -->
                        <div v-if="clientData.address.has_address && addressDisplay" class="space-y-3">
                            <div class="text-xs sm:text-sm text-slate-600 space-y-1">
                                <p
                                    v-for="(line, idx) in addressDisplay.lines"
                                    :key="idx"
                                    :class="idx === 0 ? 'font-bold text-slate-900 break-words' : 'break-words text-slate-600'"
                                >
                                    {{ line }}
                                </p>
                            </div>

                            <div v-if="addressDisplay.reference" class="pt-2 border-t border-slate-100">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 block mb-0.5">
                                    Referencias
                                </span>
                                <p class="text-xs text-slate-700 leading-relaxed break-words">
                                    {{ addressDisplay.reference }}
                                </p>
                            </div>

                            <div class="pt-2">
                                <Link
                                    :href="route('cliente.addresses.edit')"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-900 hover:text-blue-800 transition"
                                >
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    <span>Editar domicilio</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Sin Domicilio Registrado -->
                        <div v-else class="text-center py-4 space-y-3">
                            <p class="text-xs text-slate-500">
                                Aún no has registrado tu domicilio de entrega.
                            </p>
                            <div>
                                <Link
                                    :href="route('cliente.addresses.create')"
                                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-xl transition shadow-xs cursor-pointer min-h-[40px]"
                                >
                                    <i class="fa-solid fa-plus text-xs"></i>
                                    <span>Registrar domicilio</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- ========================================== -->
            <!-- PESTAÑA 2: SEGURIDAD                       -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'security'" class="space-y-6 max-w-2xl">
                <!-- Tarjeta de Contraseña en Español -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7">
                    <UpdatePasswordForm />
                </div>

                <!-- Cierre de sesión -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-bold text-slate-900">Cierre de sesión</h4>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Finaliza tu sesión actual en este navegador de manera segura.
                        </p>
                    </div>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition cursor-pointer min-h-[44px]"
                    >
                        <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                        <span>Cerrar sesión</span>
                    </Link>
                </div>
            </div>

        </div>
    </div>
</template>
