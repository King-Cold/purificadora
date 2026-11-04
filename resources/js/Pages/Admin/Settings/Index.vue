<script setup>
import { computed, ref, reactive, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    schedule: {
        type: Object,
        required: true,
    },
    bottlePresentation: {
        type: Object,
        default: null,
    },
    bottlePresentations: {
        type: Array,
        default: () => [],
    },
    max_pending_payment_bottles: {
        type: Number,
        default: 5,
    },
});

const page = usePage();

// Pestañas activas: 'bottles' | 'schedule'
const activeTab = ref('bottles');

// Flash message dismiss
const flashMessage = computed(() => page.props.flash?.success || null);
const flashError = computed(() => page.props.flash?.error || null);
const showFlash = ref(true);

watch([flashMessage, flashError], () => {
    showFlash.value = true;
});

// Breadcrumb items
const breadcrumbs = [
    { label: 'Administración', href: route('dashboard') },
    { label: 'Configuración General' },
];

/* =========================================================================
   SECCIÓN 1: HORARIO DE LA SUCURSAL Y VISTA PREVIA REACTIVA
   ========================================================================= */
const scheduleForm = useForm({
    opening_time: props.schedule.opening_time || '08:00',
    break_start_time: props.schedule.break_start_time || '13:00',
    break_end_time: props.schedule.break_end_time || '15:00',
    closing_time: props.schedule.closing_time || '19:00',
    order_cutoff_advance_minutes: props.schedule.order_cutoff_advance_minutes ?? 60,
});

// Convierte 'HH:mm' a minutos transcurridos en el día
const timeToMinutes = (timeStr) => {
    if (!timeStr || typeof timeStr !== 'string' || !timeStr.includes(':')) return null;
    const [h, m] = timeStr.split(':').map((v) => parseInt(v, 10));
    if (isNaN(h) || isNaN(m)) return null;
    return h * 60 + m;
};

// Formatea minutos a 'HH:mm'
const minutesToTime = (totalMinutes) => {
    if (totalMinutes === null || isNaN(totalMinutes)) return '--:--';
    const normalized = Math.max(0, totalMinutes);
    const h = Math.floor(normalized / 60);
    const m = normalized % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

// Cálculo y validación reactiva del horario
const scheduleAnalysis = computed(() => {
    const openMin = timeToMinutes(scheduleForm.opening_time);
    const breakStartMin = timeToMinutes(scheduleForm.break_start_time);
    const breakEndMin = timeToMinutes(scheduleForm.break_end_time);
    const closeMin = timeToMinutes(scheduleForm.closing_time);
    const advance = parseInt(scheduleForm.order_cutoff_advance_minutes, 10);

    const errors = {};

    if (openMin === null) errors.opening_time = 'La hora de apertura es requerida.';
    if (breakStartMin === null) errors.break_start_time = 'El inicio del descanso es requerido.';
    if (breakEndMin === null) errors.break_end_time = 'El fin del descanso es requerido.';
    if (closeMin === null) errors.closing_time = 'La hora de cierre es requerida.';
    if (isNaN(advance) || advance < 0) errors.order_cutoff_advance_minutes = 'La anticipación debe ser mayor o igual a 0.';

    if (Object.keys(errors).length > 0) {
        return { isValid: false, errors, cutoffTime: null };
    }

    if (openMin >= breakStartMin) {
        errors.break_start_time = 'El descanso debe iniciar después de la apertura.';
    }
    if (breakStartMin >= breakEndMin) {
        errors.break_end_time = 'El fin del descanso debe ser posterior a su inicio.';
    }
    if (breakEndMin >= closeMin) {
        errors.closing_time = 'El cierre debe ser posterior al fin del descanso.';
    }

    const cutoffMin = closeMin - advance;
    const cutoffTime = minutesToTime(cutoffMin);

    if (cutoffMin <= breakEndMin) {
        errors.order_cutoff_advance_minutes = `La hora límite (${cutoffTime}) debe ser posterior al fin del descanso (${scheduleForm.break_end_time}).`;
    }

    const isValid = Object.keys(errors).length === 0;

    return {
        isValid,
        errors,
        cutoffTime,
        morning: `${scheduleForm.opening_time} a ${scheduleForm.break_start_time}`,
        breakPeriod: `${scheduleForm.break_start_time} a ${scheduleForm.break_end_time}`,
        afternoon: `${scheduleForm.break_end_time} a ${scheduleForm.closing_time}`,
        morningOrders: `${scheduleForm.opening_time} a ${scheduleForm.break_start_time}`,
        afternoonOrders: `${scheduleForm.break_end_time} a ${cutoffTime}`,
    };
});

const submitSchedule = () => {
    scheduleForm.put(route('admin.settings.schedule.update'), {
        preserveScroll: true,
    });
};

/* =========================================================================
   SECCIÓN 2: CONFIGURACIÓN EXCLUSIVA DEL BOTELLÓN DE 20 LITROS Y PRECIOS
   ========================================================================= */
const currentBottle = computed(() => {
    return props.bottlePresentation || props.bottlePresentations?.[0] || {
        id: 1,
        name: 'Botellón 20 Litros',
        capacity: 20.00,
        unit: 'Litros',
        formatted_capacity: '20 Litros',
        refill_price: 15.00,
        store_price: 10.00,
        new_bottle_price: 95.00,
        image_path: '/images/garrafon-20l.png',
        image_url: '/images/garrafon-20l.png',
        is_available_for_delivery: true,
        is_active: true,
    };
});

const bottleForm = useForm({
    name: currentBottle.value.name || 'Botellón 20 Litros',
    refill_price: currentBottle.value.refill_price !== null ? currentBottle.value.refill_price : '',
    store_price: currentBottle.value.store_price !== null ? currentBottle.value.store_price : '',
    new_bottle_price: currentBottle.value.new_bottle_price !== null ? currentBottle.value.new_bottle_price : '',
    image_path: currentBottle.value.image_path || '/images/garrafon-20l.png',
    image: null,
    is_available_for_delivery: Boolean(currentBottle.value.is_available_for_delivery),
});

const imagePreview = ref(currentBottle.value.image_url || currentBottle.value.image_path || '/images/garrafon-20l.png');
const fileInputRef = ref(null);

const presetImages = [
    { label: 'Garrafón Estándar 20L', path: '/images/garrafon-20l.png' },
];

const selectPresetImage = (path) => {
    bottleForm.image_path = path;
    bottleForm.image = null;
    imagePreview.value = path;
};

const handleImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        bottleForm.image = file;
        bottleForm.image_path = '';
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    bottleForm.image = null;
    bottleForm.image_path = '/images/garrafon-20l.png';
    imagePreview.value = '/images/garrafon-20l.png';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitBottleForm = () => {
    if (bottleForm.image) {
        bottleForm.transform(data => ({ ...data, _method: 'PUT' })).post(route('admin.settings.bottle-presentations.update', currentBottle.value.id), {
            preserveScroll: true,
        });
    } else {
        bottleForm.put(route('admin.settings.bottle-presentations.update', currentBottle.value.id), {
            preserveScroll: true,
        });
    }
};

/* =========================================================================
   SECCIÓN 3: LÍMITE DE BOTELLONES PENDIENTES DE PAGO
   ========================================================================= */
const debtLimitForm = useForm({
    max_pending_payment_bottles: props.max_pending_payment_bottles ?? 5,
});

const submitDebtLimit = () => {
    debtLimitForm.put(route('admin.settings.debt-limit.update'), {
        preserveScroll: true,
    });
};

// Formato de moneda mexicana MXN
const formatCurrency = (val) => {
    if (val === null || val === undefined || val === '') return null;
    const num = parseFloat(val);
    if (isNaN(num)) return null;
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
};
</script>

<template>
    <Head title="Configuración General" />

    <AuthenticatedLayout>
        <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <!-- Migas de pan y Título principal -->
            <div>
                <Breadcrumbs :items="breadcrumbs" />
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-2">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
                            <i class="fa-solid fa-gear text-slate-700 text-xl"></i>
                            Configuración General
                        </h1>
                    </div>
                </div>
            </div>

            <!-- Notificación Flash (Éxito o Error) -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <div
                    v-if="showFlash && (flashMessage || flashError)"
                    class="rounded-xl border p-4 flex items-start gap-3 shadow-xs relative"
                    :class="flashMessage ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'"
                >
                    <i
                        class="text-base mt-0.5 shrink-0"
                        :class="flashMessage ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-triangle-exclamation text-rose-600'"
                    ></i>
                    <div class="flex-1 text-sm font-medium">
                        {{ flashMessage || flashError }}
                    </div>
                    <button
                        type="button"
                        @click="showFlash = false"
                        class="text-slate-400 hover:text-slate-600 transition"
                        title="Cerrar aviso"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </transition>

            <!-- Pestañas de Navegación de la Configuración -->
            <div class="border-b border-slate-200">
                <nav class="flex gap-4 sm:gap-8" aria-label="Secciones de configuración">
                    <!-- Tab: Botellón de 20 Litros -->
                    <button
                        type="button"
                        @click="activeTab = 'bottles'"
                        :class="[
                            'pb-3.5 px-1 inline-flex items-center gap-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                            activeTab === 'bottles'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <i class="fa-solid fa-bottle-water text-base"></i>
                        <span>Botellón de 20 Litros y Precios</span>
                    </button>

                    <!-- Tab: Horario de la Sucursal -->
                    <button
                        type="button"
                        @click="activeTab = 'schedule'"
                        :class="[
                            'pb-3.5 px-1 inline-flex items-center gap-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer',
                            activeTab === 'schedule'
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                        ]"
                    >
                        <i class="fa-regular fa-clock text-base"></i>
                        <span>Horario de la Sucursal</span>
                    </button>
                </nav>
            </div>

            <!-- ========================================================================= -->
            <!-- PESTAÑA 1: CONFIGURACIÓN DEL BOTELLÓN DE 20 LITROS Y PRECIOS -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'bottles'" class="space-y-6">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Formulario de Configuración de 20 Litros (7 Columnas en LG) -->
                    <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs p-6 space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fa-solid fa-bottle-water text-blue-600 text-lg"></i>
                                    <span>Configuración del Botellón de 20 Litros</span>
                                </h2>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                    Capacidad única: 20L
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                Puritronic opera exclusivamente con botellones de 20 litros. Configura aquí los precios oficiales y la disponibilidad del servicio a domicilio.
                            </p>
                        </div>

                        <form @submit.prevent="submitBottleForm" class="space-y-5">
                            <!-- Precios Principales (2 Columnas) -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Precio de Llenado en Planta -->
                                <div class="p-3.5 rounded-xl border border-slate-200/90 bg-slate-50/50 space-y-1.5">
                                    <InputLabel for="store_price" value="Precio de llenado en Planta / Sucursal" class="text-xs font-bold text-slate-800" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 font-bold text-sm">
                                            $
                                        </div>
                                        <TextInput
                                            id="store_price"
                                            v-model="bottleForm.store_price"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            class="w-full text-sm pl-7 pr-12 font-mono font-bold text-slate-900 bg-white"
                                            placeholder="10.00"
                                            required
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-semibold">
                                            MXN
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-slate-500 leading-tight">
                                        Tarifa de llenado directo en la planta física para botellones de 20L.
                                    </p>
                                    <InputError :message="bottleForm.errors.store_price" class="mt-1" />
                                </div>

                                <!-- Precio del Servicio a Domicilio -->
                                <div class="p-3.5 rounded-xl border border-blue-200/90 bg-blue-50/40 space-y-1.5">
                                    <InputLabel for="refill_price" value="Precio del servicio a domicilio" class="text-xs font-bold text-blue-950" />
                                    <div class="relative mt-1">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-blue-600 font-bold text-sm">
                                            $
                                        </div>
                                        <TextInput
                                            id="refill_price"
                                            v-model="bottleForm.refill_price"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            class="w-full text-sm pl-7 pr-12 font-mono font-bold text-blue-950 bg-white border-blue-300"
                                            placeholder="15.00"
                                            required
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-blue-500 text-xs font-semibold">
                                            MXN
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-blue-700/80 leading-tight">
                                        Tarifa por botellón de 20L entregado en ruta (fijo o de cambio).
                                    </p>
                                    <InputError :message="bottleForm.errors.refill_price" class="mt-1" />
                                </div>
                            </div>

                            <!-- Precio de Envase Nuevo (Opcional) -->
                            <div class="p-3.5 rounded-xl border border-slate-200/80 bg-white space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <InputLabel for="new_bottle_price" value="Precio de venta de envase nuevo completo (opcional)" class="text-xs font-semibold text-slate-700" />
                                    <span class="text-[10px] text-slate-400 font-medium">Con envase incluido</span>
                                </div>
                                <div class="relative mt-1 max-w-xs">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 font-bold text-sm">
                                        $
                                    </div>
                                    <TextInput
                                        id="new_bottle_price"
                                        v-model="bottleForm.new_bottle_price"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full text-sm pl-7 pr-12 font-mono text-slate-800"
                                        placeholder="95.00"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-semibold">
                                        MXN
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500">
                                    Tarifa aplicable cuando el cliente no cuenta con envase de intercambio y compra uno nuevo de 20L.
                                </p>
                                <InputError :message="bottleForm.errors.new_bottle_price" class="mt-1" />
                            </div>

                            <!-- Interruptor Comercial: Servicio a Domicilio Habilitado -->
                            <div
                                class="p-4 rounded-xl border transition-colors cursor-pointer"
                                :class="bottleForm.is_available_for_delivery ? 'bg-blue-50/70 border-blue-200' : 'bg-slate-50 border-slate-200'"
                            >
                                <label class="inline-flex items-start gap-3 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        v-model="bottleForm.is_available_for_delivery"
                                        class="rounded border-slate-300 text-blue-600 shadow-xs focus:ring-blue-500 mt-1 h-4 w-4"
                                    />
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs sm:text-sm font-bold text-slate-900">
                                                Servicio a domicilio habilitado
                                            </span>
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                                :class="bottleForm.is_available_for_delivery ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-600'"
                                            >
                                                {{ bottleForm.is_available_for_delivery ? 'Activo' : 'Suspendido' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 leading-relaxed">
                                            Permite a los clientes registrar y recibir pedidos de botellones de 20L en sus domicilios. Si se suspende, la recepción de pedidos en ruta se desactiva temporalmente como decisión de negocio sin alterar las cuentas ni los envases de los clientes.
                                        </p>
                                    </div>
                                </label>
                                <InputError :message="bottleForm.errors.is_available_for_delivery" class="mt-2" />
                            </div>

                            <!-- Imagen Oficial del Botellón -->
                            <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-xl space-y-3">
                                <InputLabel value="Imagen oficial del botellón de 20 litros" class="text-xs font-semibold text-slate-700" />
                                
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-xl bg-white border border-slate-200 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                        <img :src="imagePreview" alt="Vista previa del botellón" class="max-h-full max-w-full object-contain" />
                                    </div>
                                    <div class="space-y-2 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                v-for="preset in presetImages"
                                                :key="preset.path"
                                                type="button"
                                                @click="selectPresetImage(preset.path)"
                                                :class="[
                                                    'px-2.5 py-1 rounded-lg text-xs font-semibold border transition cursor-pointer flex items-center gap-1.5',
                                                    bottleForm.image_path === preset.path
                                                        ? 'bg-blue-50 text-blue-700 border-blue-300 ring-1 ring-blue-300'
                                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                                ]"
                                            >
                                                <i class="fa-solid fa-bottle-water text-xs text-blue-500"></i>
                                                <span>{{ preset.label }}</span>
                                            </button>

                                            <label class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 cursor-pointer flex items-center gap-1.5 transition">
                                                <i class="fa-solid fa-cloud-arrow-up text-xs text-slate-500"></i>
                                                <span>Subir imagen personalizada</span>
                                                <input
                                                    ref="fileInputRef"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                                    @change="handleImageUpload"
                                                    class="hidden"
                                                />
                                            </label>

                                            <button
                                                v-if="bottleForm.image || bottleForm.image_path !== '/images/garrafon-20l.png'"
                                                type="button"
                                                @click="removeImage"
                                                class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                                            >
                                                Restablecer por defecto
                                            </button>
                                        </div>
                                        <p class="text-[11px] text-slate-500">Formatos recomendados: PNG o WEBP con fondo transparente.</p>
                                    </div>
                                </div>
                                <InputError :message="bottleForm.errors.image || bottleForm.errors.image_path" class="mt-1" />
                            </div>

                            <!-- Botón Guardar -->
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="bottleForm.processing"
                                    class="px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2"
                                >
                                    <i v-if="bottleForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                    <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                    <span>Guardar Precios y Botellón</span>
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <!-- Vista Previa Reactiva y Resumen Operativo (5 Columnas en LG) -->
                    <div class="lg:col-span-5 space-y-6">
                        <!-- Tarjeta de Vista Previa del Botellón -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-6 space-y-5">
                            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fa-solid fa-eye text-blue-600 text-sm"></i>
                                    <span>Vista Previa del Botellón</span>
                                </h3>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                    En vivo
                                </span>
                            </div>

                            <div class="flex items-center gap-4 p-4 bg-slate-50/70 border border-slate-200/70 rounded-2xl">
                                <div class="w-20 h-20 rounded-xl bg-white border border-slate-200 p-2 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                    <img :src="imagePreview" alt="Botellón 20L" class="max-h-full max-w-full object-contain" />
                                </div>
                                <div class="space-y-1">
                                    <span class="font-extrabold text-slate-900 text-base block">Botellón de 20 Litros</span>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-200/80 text-slate-800 font-mono text-[11px] font-bold">
                                            20 Litros
                                        </span>
                                        <span
                                            v-if="bottleForm.is_available_for_delivery"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-100 text-blue-800"
                                        >
                                            <i class="fa-solid fa-truck-fast text-[9px]"></i>
                                            A domicilio y planta
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-100 text-amber-800"
                                        >
                                            <i class="fa-solid fa-store text-[9px]"></i>
                                            Solo en planta
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarifas Vigentes Calculadas -->
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                    <span class="text-slate-600 font-medium">Precio de llenado en Planta:</span>
                                    <span class="font-bold text-slate-900 font-mono text-sm">
                                        {{ formatCurrency(bottleForm.store_price) || 'Sin configurar' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                    <span class="text-blue-900 font-medium">Precio del servicio a domicilio:</span>
                                    <span class="font-bold text-blue-900 font-mono text-sm">
                                        {{ formatCurrency(bottleForm.refill_price) || 'Sin configurar' }}
                                    </span>
                                </div>
                                <div class="flex justify-between items-center py-2">
                                    <span class="text-slate-500 font-medium">Precio de envase nuevo completo:</span>
                                    <span class="font-semibold text-slate-700 font-mono">
                                        {{ formatCurrency(bottleForm.new_bottle_price) || 'Sin configurar' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Reglas Operativas del Botellón de 20 Litros -->
                        <div class="bg-gradient-to-br from-slate-50 to-blue-50/30 border border-slate-200/90 rounded-2xl p-5 space-y-3.5 text-xs">
                            <div class="flex items-center gap-2 font-bold text-slate-900">
                                <i class="fa-solid fa-circle-info text-blue-600 text-sm"></i>
                                <span>Modalidades del Botellón de 20L</span>
                            </div>
                            <p class="text-slate-600 leading-relaxed">
                                Puritronic opera con un solo tamaño de envase. Fijos y de cambio son modalidades operativas y de propiedad, no capacidades distintas:
                            </p>
                            <div class="space-y-2.5">
                                <div class="p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-slate-900">
                                        <i class="fa-solid fa-tag text-blue-600 text-xs"></i>
                                        <span>Botellones Fijos (20L)</span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 leading-normal">
                                        Propiedad exclusiva del cliente. Se recogen vacíos en su domicilio, se lavan y llenan en planta, y se devuelven al mismo cliente.
                                    </p>
                                </div>
                                <div class="p-3 bg-white rounded-xl border border-slate-200/80 shadow-2xs space-y-1">
                                    <div class="flex items-center gap-1.5 font-bold text-slate-900">
                                        <i class="fa-solid fa-repeat text-emerald-600 text-xs"></i>
                                        <span>Botellones de Cambio (20L)</span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 leading-normal">
                                        Intercambio genérico e inmediato. El cliente entrega un botellón de 20L vacío y recibe uno lleno en la misma visita.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta de Límite de Botellones Pendientes de Pago -->
                        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                                    <i class="fa-solid fa-hand-holding-dollar text-slate-500"></i>
                                    <span>Límite de Envases Pendientes</span>
                                </h3>
                            </div>
                            <form @submit.prevent="submitDebtLimit" class="space-y-3">
                                <div>
                                    <InputLabel for="debt_limit" value="Límite máximo general de botellones pendientes por cliente" class="text-xs font-medium text-slate-600" />
                                    <div class="relative mt-1 max-w-[160px]">
                                        <TextInput
                                            id="debt_limit"
                                            v-model="debtLimitForm.max_pending_payment_bottles"
                                            type="number"
                                            min="0"
                                            class="w-full text-sm font-bold text-slate-900"
                                            required
                                        />
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        Bloquea nuevos pedidos si el cliente excede este número de botellones sin liquidar.
                                    </p>
                                    <InputError :message="debtLimitForm.errors.max_pending_payment_bottles" class="mt-1" />
                                </div>
                                <div class="flex justify-end">
                                    <SecondaryButton
                                        type="submit"
                                        :disabled="debtLimitForm.processing"
                                        class="text-xs font-semibold py-1.5 px-3"
                                    >
                                        <i v-if="debtLimitForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs mr-1"></i>
                                        <span>Actualizar límite</span>
                                    </SecondaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ========================================================================= -->
            <!-- PESTAÑA 2: HORARIO DE LA SUCURSAL Y REGLAS DE PEDIDOS -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'schedule'" class="space-y-6">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <!-- Formulario de Configuración (7 Columnas en LG) -->
                    <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs p-6 space-y-6">
                        <div class="border-b border-slate-100 pb-4">
                            <h2 class="text-base font-bold text-slate-900">Configuración del Horario</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Establece las horas de apertura, periodo de descanso, cierre y margen de recepción.
                            </p>
                        </div>

                        <form @submit.prevent="submitSchedule" class="space-y-5">
                            <!-- Fila 1: Apertura y Cierre -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="opening_time" value="Hora de Apertura" class="text-xs font-semibold" />
                                    <div class="relative mt-1">
                                        <TextInput
                                            id="opening_time"
                                            v-model="scheduleForm.opening_time"
                                            type="time"
                                            class="w-full text-sm"
                                            required
                                        />
                                    </div>
                                    <InputError :message="scheduleForm.errors.opening_time || scheduleAnalysis.errors.opening_time" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="closing_time" value="Hora de Cierre" class="text-xs font-semibold" />
                                    <div class="relative mt-1">
                                        <TextInput
                                            id="closing_time"
                                            v-model="scheduleForm.closing_time"
                                            type="time"
                                            class="w-full text-sm"
                                            required
                                        />
                                    </div>
                                    <InputError :message="scheduleForm.errors.closing_time || scheduleAnalysis.errors.closing_time" class="mt-1" />
                                </div>
                            </div>

                            <!-- Fila 2: Periodo de Descanso -->
                            <div class="p-4 bg-slate-50/70 border border-slate-200/80 rounded-xl space-y-3">
                                <div class="flex items-center gap-2 text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    <i class="fa-solid fa-mug-saucer text-slate-500"></i>
                                    <span>Periodo de Descanso Intermedio</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="break_start_time" value="Inicio del Descanso" class="text-xs" />
                                        <TextInput
                                            id="break_start_time"
                                            v-model="scheduleForm.break_start_time"
                                            type="time"
                                            class="w-full mt-1 text-sm bg-white"
                                            required
                                        />
                                        <InputError :message="scheduleForm.errors.break_start_time || scheduleAnalysis.errors.break_start_time" class="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel for="break_end_time" value="Fin del Descanso" class="text-xs" />
                                        <TextInput
                                            id="break_end_time"
                                            v-model="scheduleForm.break_end_time"
                                            type="time"
                                            class="w-full mt-1 text-sm bg-white"
                                            required
                                        />
                                        <InputError :message="scheduleForm.errors.break_end_time || scheduleAnalysis.errors.break_end_time" class="mt-1" />
                                    </div>
                                </div>
                            </div>

                            <!-- Fila 3: Anticipación de Cierre de Pedidos -->
                            <div>
                                <InputLabel
                                    for="order_cutoff_advance_minutes"
                                    value="Anticipación para cerrar pedidos (minutos antes del cierre)"
                                    class="text-xs font-semibold"
                                />
                                <div class="relative mt-1 max-w-xs">
                                    <TextInput
                                        id="order_cutoff_advance_minutes"
                                        v-model="scheduleForm.order_cutoff_advance_minutes"
                                        type="number"
                                        min="0"
                                        class="w-full text-sm pl-4 pr-16"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                        minutos
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Aplica exclusivamente al cierre del día. No aplica antes del descanso intermedio.
                                </p>
                                <InputError :message="scheduleForm.errors.order_cutoff_advance_minutes || scheduleAnalysis.errors.order_cutoff_advance_minutes" class="mt-1" />
                            </div>

                            <!-- Botón Guardar -->
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                                <PrimaryButton
                                    type="submit"
                                    :disabled="scheduleForm.processing || !scheduleAnalysis.isValid"
                                    class="px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2"
                                >
                                    <i v-if="scheduleForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                    <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                    <span>Guardar Horario</span>
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <!-- Vista Previa Reactiva del Horario (5 Columnas en LG) -->
                    <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl shadow-xs p-6 space-y-5">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <i class="fa-solid fa-eye text-blue-600 text-sm"></i>
                                Vista Previa del Horario
                            </h2>
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">
                                En vivo
                            </span>
                        </div>

                        <!-- Estado Inválido / Errores de coherencia -->
                        <div
                            v-if="!scheduleAnalysis.isValid"
                            class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-900 space-y-2 text-xs"
                        >
                            <div class="flex items-center gap-2 font-bold text-amber-950">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                <span>Horario con inconsistencias</span>
                            </div>
                            <p class="text-amber-800">
                                Corrige los valores marcados para visualizar los intervalos de pedidos calculados:
                            </p>
                            <ul class="list-disc list-inside space-y-1 text-amber-900/90 font-medium">
                                <li v-for="(err, key) in scheduleAnalysis.errors" :key="key">
                                    {{ err }}
                                </li>
                            </ul>
                        </div>

                        <!-- Resumen Válido -->
                        <div v-else class="space-y-4">
                            <!-- Tarjeta de Intervalos de Recepción -->
                            <div class="bg-emerald-50/70 border border-emerald-200 rounded-xl p-4 space-y-2 text-emerald-950">
                                <div class="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-emerald-800">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-cart-arrow-down text-emerald-600"></i>
                                        Recepción de Pedidos Permitida
                                    </span>
                                </div>
                                <div class="space-y-1.5 text-xs">
                                    <div class="flex justify-between items-center py-1 border-b border-emerald-200/60 font-medium">
                                        <span class="text-emerald-800">Turno de la mañana:</span>
                                        <span class="font-bold font-mono text-emerald-950">{{ scheduleAnalysis.morningOrders }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-1 font-medium">
                                        <span class="text-emerald-800">Turno de la tarde:</span>
                                        <span class="font-bold font-mono text-emerald-950">{{ scheduleAnalysis.afternoonOrders }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Tarjeta de Horarios de Atención General -->
                            <div class="space-y-2.5 text-xs">
                                <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Atención en la mañana:</span>
                                    <span class="font-bold text-slate-800 font-mono">{{ scheduleAnalysis.morning }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Periodo de descanso:</span>
                                    <span class="font-bold text-amber-700 font-mono">{{ scheduleAnalysis.breakPeriod }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Atención en la tarde:</span>
                                    <span class="font-bold text-slate-800 font-mono">{{ scheduleAnalysis.afternoon }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                                    <span class="text-slate-500 font-medium">Hora límite de pedidos:</span>
                                    <span class="font-bold text-rose-700 font-mono">{{ scheduleAnalysis.cutoffTime }}</span>
                                </div>
                                <div class="flex justify-between items-center py-1.5">
                                    <span class="text-slate-500 font-medium">Días aplicables:</span>
                                    <span class="font-semibold text-slate-800">Lunes a Domingo</span>
                                </div>
                            </div>

                            <!-- Guía de Comprobación y Motivos -->
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                                <p class="font-semibold text-slate-700">Comportamiento en la recepción de pedidos:</p>
                                <div class="grid grid-cols-1 gap-1 text-[11px]">
                                    <div class="flex items-center gap-1.5 text-slate-600">
                                        <i class="fa-solid fa-ban text-slate-400 w-3"></i>
                                        <span>Antes de apertura:</span>
                                        <span class="font-medium text-slate-800">“La sucursal todavía no abre.”</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-600">
                                        <i class="fa-solid fa-circle-pause text-amber-500 w-3"></i>
                                        <span>En descanso:</span>
                                        <span class="font-medium text-slate-800">“La sucursal está en descanso.”</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-600">
                                        <i class="fa-solid fa-hourglass-end text-rose-500 w-3"></i>
                                        <span>Hora límite a cierre:</span>
                                        <span class="font-medium text-slate-800">“La recepción de pedidos terminó por hoy.”</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-slate-600">
                                        <i class="fa-solid fa-door-closed text-slate-500 w-3"></i>
                                        <span>Cierre en adelante:</span>
                                        <span class="font-medium text-slate-800">“La sucursal está cerrada.”</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
