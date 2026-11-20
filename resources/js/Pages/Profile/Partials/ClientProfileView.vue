<script setup>
import {
    Droplets as IconDroplets,
    Package as IconPackage,
    MapPin as IconMapPin,
    CheckCircle2 as IconCheckCircle2,
    PenSquare as IconPenSquare,
    Plus as IconPlus,
    Minus as IconMinus,
    LogOut as IconLogOut,
    ShieldCheck as IconShieldCheck,
    User as IconUser,
} from 'lucide-vue-next';
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

// Edición de Botellones (modalidad exclusiva: fijos o de cambio)
const isEditingBottles = ref(false);
const bottleRecentlySuccessful = ref(false);

const getInitialBottleType = (cust) => {
    if (Number(cust?.fixed_bottles_count || 0) > 0) return 'fixed';
    return 'exchange';
};

const selectedBottleType = ref(getInitialBottleType(props.clientData.customer));
const selectedBottleQty = ref(
    Number(props.clientData.customer?.fixed_bottles_count || 0) > 0
        ? Number(props.clientData.customer.fixed_bottles_count)
        : Number(props.clientData.customer?.exchange_bottles_count || 0)
);

const bottleForm = useForm({
    bottle_type: selectedBottleType.value,
    bottles_count: selectedBottleQty.value,
    fixed_bottles_count: props.clientData.customer.fixed_bottles_count ?? 0,
    exchange_bottles_count: props.clientData.customer.exchange_bottles_count ?? 0,
});

const syncBottleForm = () => {
    const qty = Number.isFinite(Number(selectedBottleQty.value))
        ? Math.min(500, Math.max(0, parseInt(selectedBottleQty.value, 10)))
        : 0;
    selectedBottleQty.value = qty;
    bottleForm.bottle_type = selectedBottleType.value;
    bottleForm.bottles_count = qty;
    if (selectedBottleType.value === 'fixed') {
        bottleForm.fixed_bottles_count = qty;
        bottleForm.exchange_bottles_count = 0;
    } else {
        bottleForm.fixed_bottles_count = 0;
        bottleForm.exchange_bottles_count = qty;
    }
};

watch(
    () => props.clientData.customer,
    (newCust) => {
        if (newCust && !isEditingBottles.value) {
            selectedBottleType.value = getInitialBottleType(newCust);
            selectedBottleQty.value = Number(newCust.fixed_bottles_count || 0) > 0
                ? Number(newCust.fixed_bottles_count)
                : Number(newCust.exchange_bottles_count || 0);
            bottleForm.fixed_bottles_count = newCust.fixed_bottles_count ?? 0;
            bottleForm.exchange_bottles_count = newCust.exchange_bottles_count ?? 0;
        }
    },
    { deep: true }
);

const computedEditingTotal = computed(() => {
    return Number.isFinite(Number(selectedBottleQty.value))
        ? Math.max(0, parseInt(selectedBottleQty.value, 10))
        : 0;
});

const openEditBottles = () => {
    selectedBottleType.value = getInitialBottleType(props.clientData.customer);
    selectedBottleQty.value = Number(props.clientData.customer?.fixed_bottles_count || 0) > 0
        ? Number(props.clientData.customer.fixed_bottles_count)
        : Number(props.clientData.customer?.exchange_bottles_count || 0);
    syncBottleForm();
    bottleForm.clearErrors();
    isEditingBottles.value = true;
};

const cancelEditBottles = () => {
    bottleForm.clearErrors();
    isEditingBottles.value = false;
};

const submitBottles = () => {
    syncBottleForm();
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

const currentModalityInfo = computed(() => {
    const fixed = Number(props.clientData.customer?.fixed_bottles_count || 0);
    const exchange = Number(props.clientData.customer?.exchange_bottles_count || 0);
    const total = Number(props.clientData.customer?.total_bottles || 0);

    if (fixed > 0) {
        return {
            type: 'fixed',
            label: 'Botellones Fijos',
            description: 'Tus propios envases rotulados',
            count: fixed,
            badgeClass: 'bg-indigo-100 text-indigo-900 border-indigo-200',
            panelClass: 'bg-indigo-50/60 border-indigo-200/80',
        };
    }
    if (exchange > 0) {
        return {
            type: 'exchange',
            label: 'Botellones de Cambio',
            description: 'Intercambio rápido vacío por lleno',
            count: exchange,
            badgeClass: 'bg-sky-100 text-sky-900 border-sky-200',
            panelClass: 'bg-sky-50/70 border-sky-200/80',
        };
    }
    return {
        type: 'none',
        label: 'Sin envases configurados',
        description: 'Define cuántos botellones de 20 L usas',
        count: total,
        badgeClass: 'bg-amber-100 text-amber-900 border-amber-200',
        panelClass: 'bg-amber-50/60 border-amber-200/80',
    };
});

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
    if (streetPart) lines.push(streetPart);
    if (addr.between_streets?.trim()) lines.push(`Cruzamientos: ${addr.between_streets.trim()}`);

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

const initials = computed(() => {
    const raw = form.name || user.name || 'C';
    const parts = raw.trim().split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return raw.slice(0, 2).toUpperCase();
});
</script>

<template>
    <div class="py-4 sm:py-7 bg-[#F5F4F8]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- ========================================== -->
            <!-- 1. HERO MORADO DE CUENTA                   -->
            <!-- ========================================== -->
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#1E1545] via-[#2A1E5E] to-[#3B297D] text-white shadow-md p-5 sm:p-6 border border-[#34266E]">
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute -right-10 -top-10 w-44 h-44 rounded-full bg-violet-400/15 blur-2xl"
                ></div>

                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-white/15 border border-white/25 text-white text-base sm:text-lg font-black flex items-center justify-center shrink-0">
                            {{ initials }}
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight leading-snug break-words text-white">
                                {{ form.name || user.name }}
                            </h1>
                            <p class="text-xs sm:text-sm text-violet-200 break-all">
                                {{ form.email || user.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Pestañas equilibradas -->
                    <div
                        class="grid grid-cols-2 w-full sm:w-auto sm:inline-flex p-1 rounded-2xl bg-white/15 border border-white/20 shrink-0"
                        role="tablist"
                        aria-label="Secciones de tu cuenta"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === 'personal'"
                            @click="activeTab = 'personal'"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 min-h-[40px] rounded-xl text-xs font-extrabold transition cursor-pointer"
                            :class="activeTab === 'personal'
                                ? 'bg-white text-[#1E1545] shadow-sm'
                                : 'text-violet-100 hover:text-white'"
                        >
                            <IconUser class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>Mi cuenta</span>
                        </button>

                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === 'security'"
                            @click="activeTab = 'security'"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 min-h-[40px] rounded-xl text-xs font-extrabold transition cursor-pointer"
                            :class="activeTab === 'security'
                                ? 'bg-white text-[#1E1545] shadow-sm'
                                : 'text-violet-100 hover:text-white'"
                        >
                            <IconShieldCheck class="w-3.5 h-3.5" aria-hidden="true" />
                            <span>Seguridad</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- ========================================== -->
            <!-- PESTAÑA 1: MI CUENTA                       -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'personal'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Columna Izquierda: Datos personales (7 cols) -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-[#E9E7F0] shadow-sm p-5 sm:p-7 space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-violet-100 text-[#6D28D9] flex items-center justify-center shrink-0">
                            <IconUser class="w-5 h-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="text-base sm:text-lg font-extrabold text-[#1E1545]">
                                Información personal
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Actualiza tus datos de contacto para la entrega de pedidos
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Nombre completo *" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full min-h-[44px] border-[#E9E7F0]"
                                v-model="form.name"
                                required
                                autocomplete="name"
                                placeholder="Nombre y apellidos"
                            />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="nickname" value="Apodo para entregas (opcional)" />
                            <TextInput
                                id="nickname"
                                type="text"
                                class="mt-1 block w-full min-h-[44px] border-[#E9E7F0]"
                                v-model="form.nickname"
                                placeholder="Ej. Don Pepe"
                                maxlength="100"
                            />
                            <span class="text-xs text-slate-500 mt-1 block">
                                Así podemos identificarte fácilmente al entregar.
                            </span>
                            <InputError class="mt-1" :message="form.errors.nickname" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Número de teléfono *" />
                            <TextInput
                                id="phone"
                                type="text"
                                class="mt-1 block w-full min-h-[44px] border-[#E9E7F0]"
                                v-model="form.phone"
                                required
                                autocomplete="tel"
                                placeholder="Ej. 999 123 4567"
                                maxlength="25"
                            />
                            <InputError class="mt-1" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Correo electrónico *" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full min-h-[44px] border-[#E9E7F0]"
                                v-model="form.email"
                                required
                                autocomplete="username"
                            />
                            <InputError class="mt-1" :message="form.errors.email" />

                            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="mt-3 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
                                Tu dirección de correo aún no ha sido verificada.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="underline font-bold hover:text-amber-950 ml-1 cursor-pointer"
                                >
                                    Reenviar correo de verificación
                                </Link>
                                <div v-show="status === 'verification-link-sent'" class="mt-1 font-bold text-emerald-700">
                                    Se ha enviado un nuevo enlace de verificación a tu correo.
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-[#E9E7F0]">
                            <div>
                                <span v-if="formRecentlySuccessful" class="text-xs font-bold text-emerald-700 flex items-center gap-1.5">
                                    <IconCheckCircle2 class="w-4 h-4 text-emerald-600" aria-hidden="true" />
                                    <span>Cambios guardados</span>
                                </span>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 min-h-[44px] text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] rounded-xl transition shadow-sm disabled:opacity-50 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9] focus-visible:ring-offset-2"
                            >
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Columna Derecha: Botellones y Domicilio (5 cols) -->
                <div class="lg:col-span-5 space-y-5">
                    <!-- Resumen 1: Mis Botellones -->
                    <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 rounded-xl bg-sky-100 text-[#0275B8] flex items-center justify-center shrink-0">
                                    <IconDroplets class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-extrabold text-[#1E1545]">
                                        Mis botellones
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Envases de 20 litros
                                    </p>
                                </div>
                            </div>

                            <button
                                v-if="!isEditingBottles"
                                type="button"
                                @click="openEditBottles"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[40px] text-xs font-extrabold text-[#1E1545] bg-[#F5F4F8] hover:bg-[#ECE8F8] rounded-xl transition border border-[#E9E7F0] cursor-pointer shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                            >
                                <IconPenSquare class="w-3.5 h-3.5 text-[#6D28D9]" aria-hidden="true" />
                                <span>Modificar</span>
                            </button>
                        </div>

                        <div v-if="bottleRecentlySuccessful" class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center gap-2">
                            <IconCheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" />
                            <span class="font-bold">Botellones actualizados correctamente.</span>
                        </div>

                        <!-- MODO EDICIÓN BOTELLONES -->
                        <form v-if="isEditingBottles" @submit.prevent="submitBottles" class="space-y-4 pt-1">
                            <div class="grid grid-cols-2 gap-2.5">
                                <button
                                    type="button"
                                    @click="selectedBottleType = 'exchange'; syncBottleForm()"
                                    class="p-3.5 min-h-[60px] rounded-xl border text-left transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0275B8]"
                                    :class="selectedBottleType === 'exchange'
                                        ? 'bg-sky-50 border-[#0275B8] text-[#1E1545] ring-1 ring-[#0275B8]/40'
                                        : 'bg-white border-[#E9E7F0] text-slate-600 hover:bg-[#F5F4F8]'"
                                >
                                    <span class="flex items-center gap-1.5 text-xs font-extrabold">
                                        <IconDroplets class="w-3.5 h-3.5 text-[#0275B8]" aria-hidden="true" />
                                        <span>De cambio</span>
                                    </span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5">Intercambias vacío por lleno</span>
                                </button>
                                <button
                                    type="button"
                                    @click="selectedBottleType = 'fixed'; syncBottleForm()"
                                    class="p-3.5 min-h-[60px] rounded-xl border text-left transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600"
                                    :class="selectedBottleType === 'fixed'
                                        ? 'bg-indigo-50 border-indigo-600 text-[#1E1545] ring-1 ring-indigo-500/40'
                                        : 'bg-white border-[#E9E7F0] text-slate-600 hover:bg-[#F5F4F8]'"
                                >
                                    <span class="flex items-center gap-1.5 text-xs font-extrabold">
                                        <IconPackage class="w-3.5 h-3.5 text-indigo-600" aria-hidden="true" />
                                        <span>Fijos</span>
                                    </span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5">Rotulados con tu nombre</span>
                                </button>
                            </div>
                            <InputError :message="bottleForm.errors.bottle_type" />

                            <div class="p-3.5 rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <span class="text-xs font-bold text-[#1E1545] block">
                                        Cantidad ({{ selectedBottleType === 'fixed' ? 'Fijos' : 'De cambio' }})
                                    </span>
                                    <span class="text-[11px] text-slate-500">Límite máximo por pedido</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        type="button"
                                        @click="selectedBottleQty = Math.max(0, (parseInt(selectedBottleQty) || 0) - 1); syncBottleForm()"
                                        class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#1E1545] hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Restar botellón"
                                    >
                                        <IconMinus class="w-4 h-4" aria-hidden="true" />
                                    </button>
                                    <input
                                        type="number"
                                        min="0"
                                        max="500"
                                        v-model.number="selectedBottleQty"
                                        @input="syncBottleForm"
                                        aria-label="Cantidad de botellones"
                                        class="w-16 h-11 text-center font-mono font-extrabold text-base sm:text-sm rounded-xl border border-[#E9E7F0] bg-white text-[#1E1545] tabular-nums"
                                    />
                                    <button
                                        type="button"
                                        @click="selectedBottleQty = Math.min(500, (parseInt(selectedBottleQty) || 0) + 1); syncBottleForm()"
                                        class="h-11 w-11 rounded-xl bg-white border border-[#E9E7F0] text-[#1E1545] hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                                        aria-label="Sumar botellón"
                                    >
                                        <IconPlus class="w-4 h-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                            <InputError :message="bottleForm.errors.bottles_count || bottleForm.errors.fixed_bottles_count || bottleForm.errors.exchange_bottles_count" />

                            <div class="p-3.5 rounded-xl bg-violet-50 border border-violet-200 text-[#1E1545] flex items-center justify-between text-xs font-bold">
                                <span>Modalidad activa: {{ selectedBottleType === 'fixed' ? 'Fijos' : 'De cambio' }}</span>
                                <span class="text-base font-mono font-black text-[#6D28D9] tabular-nums">{{ computedEditingTotal }} u.</span>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-[#E9E7F0]">
                                <button
                                    type="button"
                                    @click="cancelEditBottles"
                                    class="px-4 py-2.5 min-h-[44px] text-xs font-bold text-slate-700 bg-[#F5F4F8] hover:bg-[#ECEAEF] rounded-xl transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="bottleForm.processing"
                                    class="inline-flex items-center gap-1.5 px-5 py-2.5 min-h-[44px] text-xs font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] rounded-xl transition shadow-sm disabled:opacity-50 cursor-pointer"
                                >
                                    <span>{{ bottleForm.processing ? 'Guardando...' : 'Guardar botellones' }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- MODO VISTA LIMPIO: MODALIDAD ACTIVA + TOTAL -->
                        <div v-else class="rounded-2xl border p-4 flex items-center justify-between gap-3" :class="currentModalityInfo.panelClass">
                            <div class="min-w-0 space-y-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-extrabold" :class="currentModalityInfo.badgeClass">
                                    {{ currentModalityInfo.label }}
                                </span>
                                <p class="text-xs text-slate-600">
                                    {{ currentModalityInfo.description }}
                                </p>
                            </div>

                            <div class="text-right shrink-0 pl-2">
                                <span class="text-3xl font-black font-mono text-[#1E1545] tabular-nums leading-none block">
                                    {{ currentModalityInfo.count }}
                                </span>
                                <span class="text-[11px] font-bold text-slate-500">
                                    {{ currentModalityInfo.count === 1 ? 'botellón' : 'botellones' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen 2: Domicilio de entrega -->
                    <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <IconMapPin class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <h3 class="text-sm font-extrabold text-[#1E1545]">
                                    Domicilio de entrega
                                </h3>
                            </div>

                            <span
                                v-if="clientData.address.has_address && addressDisplay"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 shrink-0"
                            >
                                {{ addressDisplay.zoneLabel }}
                            </span>
                        </div>

                        <div v-if="clientData.address.has_address && addressDisplay" class="space-y-3">
                            <div class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-4 text-xs sm:text-sm text-slate-600 space-y-1">
                                <p
                                    v-for="(line, idx) in addressDisplay.lines"
                                    :key="idx"
                                    :class="idx === 0 ? 'font-extrabold text-[#1E1545] break-words' : 'break-words text-slate-600'"
                                >
                                    {{ line }}
                                </p>
                            </div>

                            <div v-if="addressDisplay.reference" class="pt-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">
                                    Referencias
                                </span>
                                <p class="text-xs text-slate-700 leading-relaxed break-words">
                                    {{ addressDisplay.reference }}
                                </p>
                            </div>

                            <div class="pt-1">
                                <Link
                                    :href="route('cliente.addresses.edit')"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-extrabold text-[#1E1545] bg-[#F5F4F8] hover:bg-[#ECE8F8] border border-[#E9E7F0] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                                >
                                    <IconPenSquare class="w-4 h-4 text-[#6D28D9]" aria-hidden="true" />
                                    <span>Editar domicilio</span>
                                </Link>
                            </div>
                        </div>

                        <div v-else class="text-center py-4 space-y-3">
                            <p class="text-xs text-slate-500">
                                Aún no has registrado tu domicilio de entrega.
                            </p>
                            <div>
                                <Link
                                    :href="route('cliente.addresses.create')"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 min-h-[44px] text-xs font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] rounded-xl transition shadow-sm cursor-pointer"
                                >
                                    <IconPlus class="w-4 h-4" aria-hidden="true" />
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
            <div v-show="activeTab === 'security'" class="space-y-5 max-w-2xl">
                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-sm p-5 sm:p-7">
                    <UpdatePasswordForm variant="client" />
                </div>

                <div class="bg-white rounded-2xl border border-[#E9E7F0] shadow-sm p-5 sm:p-7 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-[#1E1545]">Cerrar sesión</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Finaliza tu sesión actual en este dispositivo de manera segura.
                        </p>
                    </div>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] text-xs font-extrabold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition cursor-pointer shrink-0"
                    >
                        <IconLogOut class="w-4 h-4" aria-hidden="true" />
                        <span>Cerrar sesión</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
