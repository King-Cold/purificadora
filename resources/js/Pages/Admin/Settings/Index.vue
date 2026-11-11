<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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

// Pestañas principales: 'prices' | 'schedule' | 'debt'
const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (['prices', 'schedule', 'debt'].includes(tab)) return tab;
    }
    return 'prices';
};
const activeTab = ref(getInitialTab());

// Mensajes flash con descarte automático
const flashMessage = computed(() => page.props.flash?.success || null);
const flashError = computed(() => page.props.flash?.error || null);
const showFlash = ref(true);

watch([flashMessage, flashError], () => {
    showFlash.value = true;
});

const breadcrumbs = [
    { label: 'Administración', href: route('dashboard') },
    { label: 'Configuración general' },
];

/* =========================================================================
   1. PRECIOS DEL BOTELLÓN DE 20 L
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

const useStandardImage = () => {
    bottleForm.image_path = '/images/garrafon-20l.png';
    bottleForm.image = null;
    imagePreview.value = '/images/garrafon-20l.png';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const triggerImageUpload = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        bottleForm.image = file;
        bottleForm.image_path = '';
        imagePreview.value = URL.createObjectURL(file);
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

const isPriceFormDirty = computed(() => {
    const origRefill = currentBottle.value.refill_price !== null ? String(currentBottle.value.refill_price) : '';
    const origStore = currentBottle.value.store_price !== null ? String(currentBottle.value.store_price) : '';
    const origNew = currentBottle.value.new_bottle_price !== null ? String(currentBottle.value.new_bottle_price) : '';
    const origDelivery = Boolean(currentBottle.value.is_available_for_delivery);
    const origImg = currentBottle.value.image_path || '/images/garrafon-20l.png';

    return String(bottleForm.refill_price) !== origRefill ||
           String(bottleForm.store_price) !== origStore ||
           String(bottleForm.new_bottle_price) !== origNew ||
           Boolean(bottleForm.is_available_for_delivery) !== origDelivery ||
           Boolean(bottleForm.image) ||
           (bottleForm.image_path && bottleForm.image_path !== origImg);
});

/* =========================================================================
   2. HORARIO DE LA SUCURSAL
   ========================================================================= */
const scheduleForm = useForm({
    opening_time: props.schedule.opening_time || '08:00',
    break_start_time: props.schedule.break_start_time || '13:00',
    break_end_time: props.schedule.break_end_time || '15:00',
    closing_time: props.schedule.closing_time || '19:00',
    order_cutoff_advance_minutes: props.schedule.order_cutoff_advance_minutes ?? 60,
});

const timeToMinutes = (timeStr) => {
    if (!timeStr || typeof timeStr !== 'string' || !timeStr.includes(':')) return null;
    const [h, m] = timeStr.split(':').map((v) => parseInt(v, 10));
    if (isNaN(h) || isNaN(m)) return null;
    return h * 60 + m;
};

const minutesToTime = (totalMinutes) => {
    if (totalMinutes === null || isNaN(totalMinutes)) return '--:--';
    const normalized = Math.max(0, totalMinutes);
    const h = Math.floor(normalized / 60);
    const m = normalized % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

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
        branchService: `${scheduleForm.opening_time}–${scheduleForm.break_start_time} y ${scheduleForm.break_end_time}–${scheduleForm.closing_time}`,
        breakPeriod: `${scheduleForm.break_start_time}–${scheduleForm.break_end_time}`,
        orderReception: `${scheduleForm.opening_time}–${scheduleForm.break_start_time} y ${scheduleForm.break_end_time}–${cutoffTime}`,
    };
});

const isScheduleFormDirty = computed(() => {
    return scheduleForm.opening_time !== (props.schedule.opening_time || '08:00') ||
           scheduleForm.break_start_time !== (props.schedule.break_start_time || '13:00') ||
           scheduleForm.break_end_time !== (props.schedule.break_end_time || '15:00') ||
           scheduleForm.closing_time !== (props.schedule.closing_time || '19:00') ||
           Number(scheduleForm.order_cutoff_advance_minutes) !== Number(props.schedule.order_cutoff_advance_minutes ?? 60);
});

const submitSchedule = () => {
    scheduleForm.put(route('admin.settings.schedule.update'), {
        preserveScroll: true,
    });
};

/* =========================================================================
   3. LÍMITE DE FIADO
   ========================================================================= */
const debtLimitForm = useForm({
    max_pending_payment_bottles: props.max_pending_payment_bottles ?? 5,
});

const isDebtFormDirty = computed(() => {
    return Number(debtLimitForm.max_pending_payment_bottles) !== Number(props.max_pending_payment_bottles ?? 5);
});

const submitDebtLimit = () => {
    debtLimitForm.put(route('admin.settings.debt-limit.update'), {
        preserveScroll: true,
    });
};

// Formato de moneda MXN
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
    <Head title="Configuración general" />

    <AuthenticatedLayout>
        <div class="py-4 sm:py-6 px-3.5 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-5 sm:space-y-6">
            <!-- Encabezado sencillo y migas de pan -->
            <div>
                <Breadcrumbs :items="breadcrumbs" />
                <div class="mt-1 sm:mt-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                        Configuración general
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 mt-1">
                        Administra los precios, el horario y el límite de fiado de Puritronic.
                    </p>
                </div>
            </div>

            <!-- Aviso flash breve (éxito o error) -->
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
                    class="rounded-xl border p-3.5 sm:p-4 flex items-start gap-3 shadow-2xs relative"
                    :class="flashMessage ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'"
                >
                    <i
                        class="text-base mt-0.5 shrink-0"
                        :class="flashMessage ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-triangle-exclamation text-rose-600'"
                    ></i>
                    <div class="flex-1 text-xs sm:text-sm font-medium">
                        {{ flashMessage || flashError }}
                    </div>
                    <button
                        type="button"
                        @click="showFlash = false"
                        class="text-slate-400 hover:text-slate-600 transition p-1"
                        title="Cerrar aviso"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </transition>

            <!-- Tres pestañas: Precios, Horario y Fiado -->
            <div class="border-b border-slate-200 overflow-x-auto">
                <nav class="flex gap-1 sm:gap-6 min-w-max" aria-label="Secciones de configuración">
                    <!-- Pestaña: Precios -->
                    <button
                        type="button"
                        @click="activeTab = 'prices'"
                        :class="[
                            'pb-3 px-3.5 inline-flex items-center gap-2 text-sm font-semibold border-b-2 transition-all cursor-pointer min-h-[44px]',
                            activeTab === 'prices'
                                ? 'border-[#0E1E42] text-[#0E1E42]'
                                : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                        ]"
                    >
                        <i class="fa-solid fa-tag text-xs sm:text-sm"></i>
                        <span>Precios</span>
                    </button>

                    <!-- Pestaña: Horario -->
                    <button
                        type="button"
                        @click="activeTab = 'schedule'"
                        :class="[
                            'pb-3 px-3.5 inline-flex items-center gap-2 text-sm font-semibold border-b-2 transition-all cursor-pointer min-h-[44px]',
                            activeTab === 'schedule'
                                ? 'border-[#0E1E42] text-[#0E1E42]'
                                : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                        ]"
                    >
                        <i class="fa-regular fa-clock text-xs sm:text-sm"></i>
                        <span>Horario</span>
                    </button>

                    <!-- Pestaña: Fiado -->
                    <button
                        type="button"
                        @click="activeTab = 'debt'"
                        :class="[
                            'pb-3 px-3.5 inline-flex items-center gap-2 text-sm font-semibold border-b-2 transition-all cursor-pointer min-h-[44px]',
                            activeTab === 'debt'
                                ? 'border-[#0E1E42] text-[#0E1E42]'
                                : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
                        ]"
                    >
                        <i class="fa-solid fa-hand-holding-dollar text-xs sm:text-sm"></i>
                        <span>Fiado</span>
                    </button>
                </nav>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. PESTAÑA: PRECIOS DEL BOTELLÓN DE 20 L -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'prices'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Columna del formulario (7 columnas en escritorio) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div class="border-b border-slate-100 pb-3 sm:pb-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-slate-900">
                                Precios del botellón de 20 L
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Tarifas oficiales de llenado y servicio para botellones de 20 L.
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-900 border border-blue-200 shrink-0">
                            20 L
                        </span>
                    </div>

                    <form @submit.prevent="submitBottleForm" class="space-y-4 sm:space-y-5">
                        <!-- Fila 1: Llenado en Planta y Servicio a domicilio (mismo peso visual) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                            <!-- Llenado en Planta -->
                            <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-1.5">
                                <InputLabel for="store_price" value="Llenado en Planta" class="text-xs font-bold text-slate-800" />
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
                                        class="w-full text-sm pl-7 pr-14 font-mono font-bold text-slate-900 bg-white min-h-[44px]"
                                        placeholder="10.00"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-semibold">
                                        MXN
                                    </div>
                                </div>
                                <InputError :message="bottleForm.errors.store_price" class="mt-1" />
                            </div>

                            <!-- Servicio a domicilio -->
                            <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-1.5">
                                <InputLabel for="refill_price" value="Servicio a domicilio" class="text-xs font-bold text-slate-800" />
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 font-bold text-sm">
                                        $
                                    </div>
                                    <TextInput
                                        id="refill_price"
                                        v-model="bottleForm.refill_price"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full text-sm pl-7 pr-14 font-mono font-bold text-slate-900 bg-white min-h-[44px]"
                                        placeholder="15.00"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-semibold">
                                        MXN
                                    </div>
                                </div>
                                <InputError :message="bottleForm.errors.refill_price" class="mt-1" />
                            </div>
                        </div>

                        <!-- Fila 2: Envase nuevo completo · Opcional -->
                        <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200/80 bg-white space-y-1.5">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <InputLabel for="new_bottle_price" value="Envase nuevo completo · Opcional" class="text-xs font-semibold text-slate-700" />
                                <span class="text-[11px] text-slate-500">Incluye el envase de 20 L</span>
                            </div>
                            <div class="relative mt-1 max-w-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 font-bold text-sm">
                                    $
                                </div>
                                <TextInput
                                    id="new_bottle_price"
                                    v-model="bottleForm.new_bottle_price"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full text-sm pl-7 pr-14 font-mono text-slate-800 min-h-[44px]"
                                    placeholder="95.00"
                                />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs font-semibold">
                                    MXN
                                </div>
                            </div>
                            <InputError :message="bottleForm.errors.new_bottle_price" class="mt-1" />
                        </div>

                        <!-- Fila 3: Fila compacta de Servicio a domicilio con interruptor -->
                        <div
                            class="p-3.5 sm:p-4 rounded-xl border transition-colors flex items-center justify-between gap-3"
                            :class="bottleForm.is_available_for_delivery ? 'bg-blue-50/50 border-blue-200' : 'bg-slate-50 border-slate-200'"
                        >
                            <div class="space-y-0.5 min-w-0 pr-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs sm:text-sm font-bold text-slate-900">
                                        Servicio a domicilio
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] sm:text-[11px] font-bold"
                                        :class="bottleForm.is_available_for_delivery ? 'bg-blue-100 text-blue-900' : 'bg-slate-200 text-slate-700'"
                                    >
                                        {{ bottleForm.is_available_for_delivery ? 'Activo' : 'Suspendido' }}
                                    </span>
                                </div>
                                <p class="text-[11px] sm:text-xs text-slate-600">
                                    Si se suspende, los clientes no podrán solicitar entregas a domicilio en ruta.
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="bottleForm.is_available_for_delivery"
                                @click="bottleForm.is_available_for_delivery = !bottleForm.is_available_for_delivery"
                                class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
                                :class="bottleForm.is_available_for_delivery ? 'bg-[#0E1E42]' : 'bg-slate-300'"
                                title="Alternar disponibilidad de servicio a domicilio"
                            >
                                <span
                                    class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                                    :class="bottleForm.is_available_for_delivery ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <!-- Fila 4: Bloque integrado de imagen -->
                        <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 bg-slate-50/70 space-y-3">
                            <InputLabel value="Imagen del botellón" class="text-xs font-semibold text-slate-700" />
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-white border border-slate-200 p-1.5 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                    <img :src="imagePreview" alt="Miniatura 20 L" class="max-h-full max-w-full object-contain" />
                                </div>
                                <div class="flex-1 space-y-2 min-w-0">
                                    <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2">
                                        <button
                                            type="button"
                                            @click="useStandardImage"
                                            class="w-full sm:w-auto px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer min-h-[36px] inline-flex items-center justify-center"
                                            :class="bottleForm.image_path === '/images/garrafon-20l.png' && !bottleForm.image
                                                ? 'bg-blue-50 text-blue-900 border-blue-300 ring-1 ring-blue-300'
                                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                                        >
                                            <i class="fa-solid fa-bottle-water text-xs mr-1 text-blue-600"></i>
                                            Usar imagen estándar
                                        </button>

                                        <button
                                            type="button"
                                            @click="triggerImageUpload"
                                            class="w-full sm:w-auto px-2.5 sm:px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 cursor-pointer min-h-[36px] transition inline-flex items-center justify-center gap-1.5"
                                        >
                                            <i class="fa-solid fa-cloud-arrow-up text-xs text-slate-500"></i>
                                            Cambiar imagen
                                        </button>

                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                            @change="handleImageUpload"
                                            class="hidden"
                                        />
                                    </div>
                                    <p class="text-[11px] text-slate-500">Formato recomendado: PNG o WEBP con fondo transparente.</p>
                                </div>
                            </div>
                            <InputError :message="bottleForm.errors.image || bottleForm.errors.image_path" class="mt-1" />
                        </div>

                        <!-- Botón de guardado y texto de ayuda -->
                        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-xs text-slate-500">
                                Guarda los precios, la disponibilidad del servicio y la imagen seleccionada.
                            </p>
                            <PrimaryButton
                                type="submit"
                                :disabled="bottleForm.processing"
                                class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 min-h-[44px] bg-[#0E1E42] hover:bg-[#142858]"
                            >
                                <i v-if="bottleForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>Guardar precios y servicio</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Columna de resumen (5 columnas en escritorio) -->
                <div class="lg:col-span-5 space-y-4 sm:space-y-5">
                    <!-- Tarjeta de vista previa -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-4">
                        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <i class="fa-solid fa-eye text-blue-900 text-xs"></i>
                                <span>{{ isPriceFormDirty ? 'Vista previa de los cambios' : 'Vista previa' }}</span>
                            </h3>
                            <span
                                class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md"
                                :class="isPriceFormDirty ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ isPriceFormDirty ? 'Sin guardar' : 'En vivo' }}
                            </span>
                        </div>

                        <!-- Resumen de imagen y disponibilidad -->
                        <div class="flex items-center gap-3.5 p-3.5 bg-slate-50/80 border border-slate-200/80 rounded-xl">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-xl bg-white border border-slate-200 p-1.5 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                <img :src="imagePreview" alt="Botellón 20 L" class="max-h-full max-w-full object-contain" />
                            </div>
                            <div class="space-y-1 min-w-0">
                                <span class="font-bold text-slate-900 text-sm block">Botellón de 20 L</span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span
                                        v-if="bottleForm.is_available_for_delivery"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-blue-100 text-blue-900"
                                    >
                                        <i class="fa-solid fa-truck-fast text-[9px]"></i>
                                        A domicilio y planta
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-100 text-amber-900"
                                    >
                                        <i class="fa-solid fa-store text-[9px]"></i>
                                        Solo en planta
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tarifas vigentes calculadas -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-slate-600 font-medium">Llenado en Planta:</span>
                                <span class="font-bold text-slate-900 font-mono text-sm">
                                    {{ formatCurrency(bottleForm.store_price) || 'Sin configurar' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                <span class="text-blue-900 font-medium">Servicio a domicilio:</span>
                                <span class="font-bold text-blue-900 font-mono text-sm">
                                    {{ formatCurrency(bottleForm.refill_price) || 'Sin configurar' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-slate-500 font-medium">Envase nuevo completo:</span>
                                <span class="font-semibold text-slate-700 font-mono">
                                    {{ formatCurrency(bottleForm.new_bottle_price) || 'Sin configurar' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ayuda desplegable: Fijos y de cambio -->
                    <details class="group bg-slate-50/80 border border-slate-200/90 rounded-2xl p-3.5 sm:p-4 text-xs transition">
                        <summary class="flex items-center justify-between font-bold text-slate-800 cursor-pointer list-none select-none">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-circle-question text-blue-900 text-sm"></i>
                                ¿Cómo funcionan los botellones fijos y de cambio?
                            </span>
                            <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform duration-200 group-open:rotate-180"></i>
                        </summary>
                        <div class="mt-3 pt-3 border-t border-slate-200/80 space-y-2.5 text-slate-600 leading-relaxed">
                            <p>
                                Ambos comparten la misma capacidad de 20 L y el mismo precio. Describen únicamente la propiedad del envase:
                            </p>
                            <div class="p-2.5 bg-white rounded-xl border border-slate-200/80 space-y-1">
                                <p class="font-bold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-tag text-blue-800 text-[11px]"></i>
                                    Botellones fijos (20 L)
                                </p>
                                <p class="text-[11px] text-slate-600">
                                    Propiedad exclusiva del cliente. Se recogen vacíos, se lavan y llenan en planta, y se entregan al mismo cliente.
                                </p>
                            </div>
                            <div class="p-2.5 bg-white rounded-xl border border-slate-200/80 space-y-1">
                                <p class="font-bold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-repeat text-emerald-700 text-[11px]"></i>
                                    Botellones de cambio (20 L)
                                </p>
                                <p class="text-[11px] text-slate-600">
                                    Intercambio genérico e inmediato. El cliente entrega un botellón vacío y recibe uno lleno en la misma visita.
                                </p>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. PESTAÑA: HORARIO DE LA SUCURSAL -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'schedule'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Columna del formulario (7 columnas en escritorio) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div class="border-b border-slate-100 pb-3 sm:pb-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Horario de la sucursal
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Organizado en apertura y cierre, descanso y recepción de pedidos.
                        </p>
                    </div>

                    <form @submit.prevent="submitSchedule" class="space-y-5 sm:space-y-6">
                        <!-- Grupo 1: Apertura y cierre -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-door-open text-slate-400"></i>
                                <span>Apertura y cierre</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                                <div>
                                    <InputLabel for="opening_time" value="Hora de apertura" class="text-xs font-medium" />
                                    <TextInput
                                        id="opening_time"
                                        v-model="scheduleForm.opening_time"
                                        type="time"
                                        class="w-full mt-1 text-sm min-h-[44px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.opening_time || scheduleAnalysis.errors.opening_time" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="closing_time" value="Hora de cierre" class="text-xs font-medium" />
                                    <TextInput
                                        id="closing_time"
                                        v-model="scheduleForm.closing_time"
                                        type="time"
                                        class="w-full mt-1 text-sm min-h-[44px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.closing_time || scheduleAnalysis.errors.closing_time" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- Grupo 2: Descanso -->
                        <div class="p-3.5 sm:p-4 bg-slate-50/70 border border-slate-200/80 rounded-xl space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-mug-saucer text-slate-400"></i>
                                <span>Descanso</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 sm:gap-4">
                                <div>
                                    <InputLabel for="break_start_time" value="Inicio del descanso" class="text-xs font-medium" />
                                    <TextInput
                                        id="break_start_time"
                                        v-model="scheduleForm.break_start_time"
                                        type="time"
                                        class="w-full mt-1 text-sm bg-white min-h-[44px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.break_start_time || scheduleAnalysis.errors.break_start_time" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="break_end_time" value="Fin del descanso" class="text-xs font-medium" />
                                    <TextInput
                                        id="break_end_time"
                                        v-model="scheduleForm.break_end_time"
                                        type="time"
                                        class="w-full mt-1 text-sm bg-white min-h-[44px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.break_end_time || scheduleAnalysis.errors.break_end_time" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- Grupo 3: Recepción de pedidos -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-cart-shopping text-slate-400"></i>
                                <span>Recepción de pedidos</span>
                            </h3>
                            <div>
                                <InputLabel
                                    for="order_cutoff_advance_minutes"
                                    :value="`Dejar de recibir pedidos ${scheduleForm.order_cutoff_advance_minutes || 60} minutos antes del cierre`"
                                    class="text-xs font-semibold text-slate-800"
                                />
                                <div class="relative mt-1 max-w-xs">
                                    <TextInput
                                        id="order_cutoff_advance_minutes"
                                        v-model="scheduleForm.order_cutoff_advance_minutes"
                                        type="number"
                                        min="0"
                                        class="w-full text-sm pl-4 pr-16 min-h-[44px]"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                        minutos
                                    </div>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Aplica exclusivamente al cierre de la jornada para permitir la preparación y entrega en ruta.
                                </p>
                                <InputError :message="scheduleForm.errors.order_cutoff_advance_minutes || scheduleAnalysis.errors.order_cutoff_advance_minutes" class="mt-1" />
                            </div>
                        </div>

                        <!-- Botón de guardado -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                            <PrimaryButton
                                type="submit"
                                :disabled="scheduleForm.processing || !scheduleAnalysis.isValid"
                                class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 min-h-[44px] bg-[#0E1E42] hover:bg-[#142858]"
                            >
                                <i v-if="scheduleForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>Guardar horario</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Columna de resumen (5 columnas en escritorio) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-4 sm:space-y-5">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-eye text-blue-900 text-xs"></i>
                            <span>{{ isScheduleFormDirty ? 'Vista previa de los cambios' : 'Vista previa del horario' }}</span>
                        </h3>
                        <span
                            class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md"
                            :class="isScheduleFormDirty ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ isScheduleFormDirty ? 'Sin guardar' : 'En vivo' }}
                        </span>
                    </div>

                    <!-- Estado con error de coherencia -->
                    <div
                        v-if="!scheduleAnalysis.isValid"
                        class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 sm:p-4 text-amber-900 space-y-2 text-xs"
                    >
                        <div class="flex items-center gap-2 font-bold text-amber-950">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                            <span>Horario con inconsistencias</span>
                        </div>
                        <ul class="list-disc list-inside space-y-1 text-amber-900/90 font-medium">
                            <li v-for="(err, key) in scheduleAnalysis.errors" :key="key">
                                {{ err }}
                            </li>
                        </ul>
                    </div>

                    <!-- Resumen limpio y estructurado -->
                    <div v-else class="space-y-4">
                        <!-- Destacado: Pedidos hasta las XX:XX -->
                        <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-blue-900 text-white flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-cart-arrow-down text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="text-xs font-bold text-blue-900 block truncate">
                                        Pedidos hasta las {{ scheduleAnalysis.cutoffTime }}
                                    </span>
                                    <span class="text-[11px] text-blue-800/80 block truncate">
                                        Límite de recepción por la tarde
                                    </span>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-slate-500 shrink-0">
                                Lunes a domingo
                            </span>
                        </div>

                        <!-- Tabla comparativa: Atención vs Descanso vs Recepción de pedidos -->
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                                    <tr>
                                        <th scope="col" class="py-2.5 px-3 font-bold">Concepto</th>
                                        <th scope="col" class="py-2.5 px-3 font-bold">Horario</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-mono text-[11px] sm:text-xs">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-2.5 px-3 font-sans font-medium text-slate-800">
                                            Atención en sucursal
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-900 font-semibold">
                                            {{ scheduleAnalysis.branchService }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 bg-slate-50/30">
                                        <td class="py-2.5 px-3 font-sans font-medium text-slate-700">
                                            Descanso
                                        </td>
                                        <td class="py-2.5 px-3 text-amber-700 font-semibold">
                                            {{ scheduleAnalysis.breakPeriod }}
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-slate-50/50 bg-blue-50/30">
                                        <td class="py-2.5 px-3 font-sans font-bold text-blue-950">
                                            Recepción de pedidos
                                        </td>
                                        <td class="py-2.5 px-3 text-blue-900 font-bold">
                                            {{ scheduleAnalysis.orderReception }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. PESTAÑA: FIADO (LÍMITE DE BOTELLONES PENDIENTES) -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'debt'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Columna del formulario (7 columnas en escritorio) -->
                <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div class="border-b border-slate-100 pb-3 sm:pb-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Límite de fiado
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Control general de botellones pendientes de pago por cliente.
                        </p>
                    </div>

                    <form @submit.prevent="submitDebtLimit" class="space-y-4 sm:space-y-5">
                        <div class="p-3.5 sm:p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                            <InputLabel
                                for="debt_limit"
                                value="Máximo de botellones pendientes de pago por cliente"
                                class="text-xs font-bold text-slate-800"
                            />
                            <div class="relative mt-1 max-w-xs">
                                <TextInput
                                    id="debt_limit"
                                    v-model="debtLimitForm.max_pending_payment_bottles"
                                    type="number"
                                    min="0"
                                    class="w-full text-sm font-mono font-bold text-slate-900 bg-white min-h-[44px]"
                                    required
                                />
                            </div>
                            <p class="text-xs text-slate-600 leading-normal">
                                Al alcanzar el límite, el cliente puede recibir nuevos pedidos con pago inmediato.
                            </p>
                            <InputError :message="debtLimitForm.errors.max_pending_payment_bottles" class="mt-1" />
                        </div>

                        <!-- Botón de guardado -->
                        <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                            <PrimaryButton
                                type="submit"
                                :disabled="debtLimitForm.processing"
                                class="w-full sm:w-auto px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 min-h-[44px] bg-[#0E1E42] hover:bg-[#142858]"
                            >
                                <i v-if="debtLimitForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                <i v-else class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>Guardar límite de fiado</span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Columna de resumen (5 columnas en escritorio) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl shadow-xs p-4 sm:p-6 space-y-4">
                    <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-eye text-blue-900 text-xs"></i>
                            <span>{{ isDebtFormDirty ? 'Vista previa de los cambios' : 'Resumen del límite' }}</span>
                        </h3>
                        <span
                            class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md"
                            :class="isDebtFormDirty ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ isDebtFormDirty ? 'Sin guardar' : 'Vigente' }}
                        </span>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-xl space-y-2">
                        <span class="text-xs text-slate-500 font-medium">Límite configurado</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-900 font-mono">
                                {{ debtLimitForm.max_pending_payment_bottles }}
                            </span>
                            <span class="text-xs font-semibold text-slate-600">botellones</span>
                        </div>
                    </div>

                    <div class="text-xs text-slate-600 leading-relaxed space-y-2">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-shield-halved text-blue-900 text-xs mt-0.5 shrink-0"></i>
                            <span>
                                Protege la cartera de clientes evitando que un domicilio acumule envases sin liquidar.
                            </span>
                        </div>
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-circle-check text-emerald-600 text-xs mt-0.5 shrink-0"></i>
                            <span>
                                No interrumpe el abastecimiento: si el cliente alcanza este límite, el repartidor puede cobrar en efectivo o transferencia en la entrega para surtir el pedido.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
