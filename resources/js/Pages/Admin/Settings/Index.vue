<script setup>
import { computed, ref, reactive, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    schedule: {
        type: Object,
        required: true,
    },
    bottlePresentations: {
        type: Array,
        default: () => [],
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
   SECCIÓN 2: CATÁLOGO DE ENVASES Y PRECIOS
   ========================================================================= */
const isPresentationModalOpen = ref(false);
const editingPresentation = ref(null);
const imagePreview = ref(null);
const fileInputRef = ref(null);

const presetImages = [
    { label: 'Garrafón 20L', path: '/images/garrafon-20l.png' },
    { label: 'Garrafón 10L', path: '/images/garrafon-10l.png' },
];

const presentationForm = useForm({
    name: '',
    capacity: '',
    unit: 'Litros',
    refill_price: '',
    store_price: '',
    new_bottle_price: '',
    image_path: '',
    image: null,
    is_available_for_delivery: false,
    is_active: true,
});

const selectPresetImage = (path) => {
    presentationForm.image_path = path;
    presentationForm.image = null;
    imagePreview.value = path;
};

const handleImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        presentationForm.image = file;
        presentationForm.image_path = '';
        imagePreview.value = URL.createObjectURL(file);
    }
};

const removeImage = () => {
    presentationForm.image = null;
    presentationForm.image_path = '';
    imagePreview.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const openCreatePresentationModal = () => {
    editingPresentation.value = null;
    presentationForm.reset();
    presentationForm.clearErrors();
    presentationForm.unit = 'Litros';
    presentationForm.is_available_for_delivery = false;
    presentationForm.is_active = true;
    presentationForm.image_path = '';
    presentationForm.image = null;
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
    isPresentationModalOpen.value = true;
};

const openEditPresentationModal = (presentation) => {
    editingPresentation.value = presentation;
    presentationForm.clearErrors();
    presentationForm.name = presentation.name;
    presentationForm.capacity = presentation.capacity;
    presentationForm.unit = presentation.unit;
    presentationForm.refill_price = presentation.refill_price !== null ? presentation.refill_price : '';
    presentationForm.store_price = presentation.store_price !== null ? presentation.store_price : '';
    presentationForm.new_bottle_price = presentation.new_bottle_price !== null ? presentation.new_bottle_price : '';
    presentationForm.image_path = presentation.image_path || '';
    presentationForm.image = null;
    imagePreview.value = presentation.image_url || presentation.image_path || null;
    presentationForm.is_available_for_delivery = Boolean(presentation.is_available_for_delivery);
    presentationForm.is_active = presentation.is_active;
    if (fileInputRef.value) fileInputRef.value.value = '';
    isPresentationModalOpen.value = true;
};

const closePresentationModal = () => {
    isPresentationModalOpen.value = false;
    editingPresentation.value = null;
    presentationForm.reset();
    presentationForm.clearErrors();
    imagePreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submitPresentationForm = () => {
    if (editingPresentation.value) {
        if (presentationForm.image) {
            presentationForm.transform(data => ({ ...data, _method: 'PUT' })).post(route('admin.settings.bottle-presentations.update', editingPresentation.value.id), {
                preserveScroll: true,
                onSuccess: () => closePresentationModal(),
            });
        } else {
            presentationForm.put(route('admin.settings.bottle-presentations.update', editingPresentation.value.id), {
                preserveScroll: true,
                onSuccess: () => closePresentationModal(),
            });
        }
    } else {
        presentationForm.post(route('admin.settings.bottle-presentations.store'), {
            preserveScroll: true,
            onSuccess: () => closePresentationModal(),
        });
    }
};

// Modal de confirmación para activar/desactivar
const isToggleStatusModalOpen = ref(false);
const presentationToToggle = ref(null);
const isTogglingStatus = ref(false);

const openToggleStatusModal = (presentation) => {
    presentationToToggle.value = presentation;
    isToggleStatusModalOpen.value = true;
};

const closeToggleStatusModal = () => {
    isToggleStatusModalOpen.value = false;
    presentationToToggle.value = null;
    isTogglingStatus.value = false;
};

const confirmToggleStatus = () => {
    if (!presentationToToggle.value) return;

    isTogglingStatus.value = true;
    router.patch(
        route('admin.settings.bottle-presentations.toggle-status', presentationToToggle.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => closeToggleStatusModal(),
        }
    );
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

                    <!-- Botón de acción contextual según pestaña -->
                    <div v-if="activeTab === 'bottles'" class="shrink-0">
                        <button
                            type="button"
                            @click="openCreatePresentationModal"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer active:scale-[0.98]"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Agregar envase</span>
                        </button>
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
                    <!-- Tab: Envases y Precios -->
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
                        <span>Envases y Precios</span>
                        <span
                            class="ml-1.5 py-0.5 px-2 rounded-full text-xs font-bold"
                            :class="activeTab === 'bottles' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ bottlePresentations.length }}
                        </span>
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
            <!-- PESTAÑA 1: CATÁLOGO DE ENVASES Y PRECIOS -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'bottles'" class="space-y-6">

                <!-- Tabla de Presentaciones -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Catálogo de Presentaciones</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Lista de envases registrados y sus tarifas de rellenado y venta de envase nuevo.
                            </p>
                        </div>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-if="bottlePresentations.length === 0" class="py-16 px-4 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                            <i class="fa-solid fa-bottle-water text-2xl"></i>
                        </div>
                        <h3 class="text-base font-semibold text-slate-900">No hay presentaciones registradas</h3>
                        <p class="text-sm text-slate-500 max-w-md mx-auto mt-1 mb-5">
                            Aún no se ha configurado ninguna presentación de envase. Comienza agregando la primera para definir sus tarifas de rellenado y envase nuevo.
                        </p>
                        <button
                            type="button"
                            @click="openCreatePresentationModal"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-xs transition cursor-pointer"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Agregar primer envase</span>
                        </button>
                    </div>

                    <!-- Tabla o Tarjetas de Datos -->
                    <div v-else>
                        <!-- VISTA MÓVIL (PANTALLAS < MD): TARJETAS DE ENVASES -->
                        <div class="md:hidden divide-y divide-slate-100">
                            <div
                                v-for="item in bottlePresentations"
                                :key="item.id"
                                class="p-4 space-y-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-14 h-14 rounded-xl bg-slate-50 border border-slate-200/80 p-1 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                                            <img
                                                v-if="item.image_url"
                                                :src="item.image_url"
                                                :alt="item.name"
                                                class="max-h-full max-w-full object-contain"
                                            />
                                            <i v-else class="fa-solid fa-bottle-water text-slate-400 text-xl"></i>
                                        </div>
                                        <div>
                                            <span class="font-bold text-sm text-slate-900 block leading-tight">{{ item.name }}</span>
                                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px] font-bold">
                                                    {{ item.formatted_capacity }}
                                                </span>
                                                <span
                                                    v-if="item.is_available_for_delivery"
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200"
                                                >
                                                    <i class="fa-solid fa-truck-fast text-[9px]"></i>
                                                    A domicilio
                                                </span>
                                                <span
                                                    v-else
                                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                                                >
                                                    <i class="fa-solid fa-store text-[9px]"></i>
                                                    Solo sucursal
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <span
                                        v-if="item.is_active"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 shrink-0"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        Activo
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 shrink-0"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Inactivo
                                    </span>
                                </div>

                                <!-- Precios en Grid Móvil -->
                                <div class="grid grid-cols-3 gap-2 p-2.5 bg-slate-50/80 rounded-xl border border-slate-100 text-xs text-center">
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-400">A Domicilio</span>
                                        <span v-if="item.is_available_for_delivery && item.has_refill_price" class="text-blue-900 font-bold font-mono">
                                            {{ formatCurrency(item.refill_price) }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10px]">N/A</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-400">Sucursal</span>
                                        <span v-if="item.has_store_price" class="text-emerald-700 font-bold font-mono">
                                            {{ formatCurrency(item.store_price) }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10px]">--</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-400">Envase Nuevo</span>
                                        <span v-if="item.has_new_bottle_price" class="text-slate-800 font-bold font-mono">
                                            {{ formatCurrency(item.new_bottle_price) }}
                                        </span>
                                        <span v-else class="text-slate-400 text-[10px]">--</span>
                                    </div>
                                </div>

                                <!-- Acciones Móvil -->
                                <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                                    <button
                                        type="button"
                                        @click="openEditPresentationModal(item)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:text-blue-900 bg-slate-100 hover:bg-blue-50 rounded-xl transition"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                        <span>Editar</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="openToggleStatusModal(item)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition"
                                        :class="item.is_active ? 'text-rose-700 bg-rose-50 hover:bg-rose-100' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'"
                                    >
                                        <i :class="item.is_active ? 'fa-solid fa-toggle-on text-rose-600' : 'fa-solid fa-toggle-off text-emerald-600'" class="text-xs"></i>
                                        <span>{{ item.is_active ? 'Desactivar' : 'Activar' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- VISTA ESCRITORIO (PANTALLAS >= MD): TABLA -->
                        <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-4 sm:px-6">Envase</th>
                                    <th class="py-3 px-4">Capacidad</th>
                                    <th class="py-3 px-4">A Domicilio</th>
                                    <th class="py-3 px-4">En Sucursal</th>
                                    <th class="py-3 px-4">Envase Nuevo</th>
                                    <th class="py-3 px-4">Estado</th>
                                    <th class="py-3 px-4 sm:px-6 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="item in bottlePresentations"
                                    :key="item.id"
                                    class="hover:bg-slate-50/60 transition-colors"
                                >
                                    <!-- Nombre e Imagen -->
                                    <td class="py-3.5 px-4 sm:px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-200/80 p-1 flex items-center justify-center shrink-0 overflow-hidden">
                                                <img
                                                    v-if="item.image_url"
                                                    :src="item.image_url"
                                                    :alt="item.name"
                                                    class="max-h-full max-w-full object-contain"
                                                />
                                                <i v-else class="fa-solid fa-bottle-water text-slate-400 text-lg"></i>
                                            </div>
                                            <div>
                                                <span class="font-semibold text-slate-900 block">{{ item.name }}</span>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[11px] text-slate-400">ID: #{{ item.id }}</span>
                                                    <span
                                                        v-if="item.is_available_for_delivery"
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200"
                                                    >
                                                        <i class="fa-solid fa-truck-fast text-[9px]"></i>
                                                        A domicilio
                                                    </span>
                                                    <span
                                                        v-else
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                                                    >
                                                        <i class="fa-solid fa-store text-[9px]"></i>
                                                        Solo sucursal
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Capacidad -->
                                    <td class="py-3.5 px-4 text-slate-600 font-mono text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium">
                                            {{ item.formatted_capacity }}
                                        </span>
                                    </td>

                                    <!-- Precio a Domicilio -->
                                    <td class="py-3.5 px-4 font-medium">
                                        <template v-if="item.is_available_for_delivery">
                                            <span
                                                v-if="item.has_refill_price"
                                                class="text-blue-700 font-mono font-semibold"
                                            >
                                                {{ formatCurrency(item.refill_price) }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200"
                                            >
                                                Sin configurar
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500">
                                                No aplica (solo sucursal)
                                            </span>
                                        </template>
                                    </td>

                                    <!-- Precio en Sucursal -->
                                    <td class="py-3.5 px-4 font-medium">
                                        <span
                                            v-if="item.has_store_price"
                                            class="text-emerald-700 font-mono font-semibold"
                                        >
                                            {{ formatCurrency(item.store_price) }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"
                                        >
                                            Sin configurar
                                        </span>
                                    </td>

                                    <!-- Precio de Envase Nuevo -->
                                    <td class="py-3.5 px-4 font-medium">
                                        <span
                                            v-if="item.has_new_bottle_price"
                                            class="text-slate-800 font-mono font-semibold"
                                        >
                                            {{ formatCurrency(item.new_bottle_price) }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-500"
                                        >
                                            Sin configurar
                                        </span>
                                    </td>

                                    <!-- Estado -->
                                    <td class="py-3.5 px-4">
                                        <span
                                            v-if="item.is_active"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            Activo
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Inactivo
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="py-3.5 px-4 sm:px-6 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <!-- Botón Editar -->
                                            <button
                                                type="button"
                                                @click="openEditPresentationModal(item)"
                                                class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition cursor-pointer"
                                                title="Editar presentación"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </button>

                                            <!-- Botón Activar / Desactivar -->
                                            <button
                                                type="button"
                                                @click="openToggleStatusModal(item)"
                                                :class="[
                                                    'h-8 px-2.5 rounded-lg flex items-center gap-1.5 text-xs font-semibold transition cursor-pointer',
                                                    item.is_active
                                                        ? 'text-slate-500 hover:text-rose-600 hover:bg-rose-50'
                                                        : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                                                ]"
                                                :title="item.is_active ? 'Desactivar presentación' : 'Activar presentación'"
                                            >
                                                <i :class="item.is_active ? 'fa-solid fa-toggle-on text-emerald-600 text-sm' : 'fa-solid fa-toggle-off text-slate-400 text-sm'"></i>
                                                <span>{{ item.is_active ? 'Desactivar' : 'Activar' }}</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
                                <div class="mt-1 relative rounded-xl shadow-xs">
                                    <TextInput
                                        id="order_cutoff_advance_minutes"
                                        v-model="scheduleForm.order_cutoff_advance_minutes"
                                        type="number"
                                        min="0"
                                        max="1440"
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

        <!-- ========================================================================= -->
        <!-- MODAL: AGREGAR / EDITAR PRESENTACIÓN DE ENVASE -->
        <!-- ========================================================================= -->
        <Modal :show="isPresentationModalOpen" @close="closePresentationModal" max-width="lg">
            <div class="p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-bottle-water text-blue-600"></i>
                        <span>{{ editingPresentation ? 'Editar Presentación' : 'Agregar Presentación' }}</span>
                    </h3>
                    <button
                        type="button"
                        @click="closePresentationModal"
                        class="text-slate-400 hover:text-slate-600 transition"
                        title="Cerrar"
                    >
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>

                <form @submit.prevent="submitPresentationForm" class="mt-5 space-y-4">
                    <!-- Nombre del Envase -->
                    <div>
                        <InputLabel for="pres_name" value="Nombre del Envase" class="text-xs font-semibold" />
                        <TextInput
                            id="pres_name"
                            v-model="presentationForm.name"
                            type="text"
                            class="w-full mt-1 text-sm"
                            placeholder="Ej. Garrafón Estándar"
                            required
                            maxlength="100"
                        />
                        <InputError :message="presentationForm.errors.name" class="mt-1" />
                    </div>

                    <!-- Capacidad y Unidad de Medida -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="pres_capacity" value="Capacidad" class="text-xs font-semibold" />
                            <TextInput
                                id="pres_capacity"
                                v-model="presentationForm.capacity"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full mt-1 text-sm"
                                placeholder="Ej. 20"
                                required
                            />
                            <InputError :message="presentationForm.errors.capacity" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="pres_unit" value="Unidad de Medida" class="text-xs font-semibold" />
                            <TextInput
                                id="pres_unit"
                                v-model="presentationForm.unit"
                                type="text"
                                class="w-full mt-1 text-sm"
                                placeholder="Ej. Litros"
                                required
                                maxlength="20"
                            />
                            <InputError :message="presentationForm.errors.unit" class="mt-1" />
                        </div>
                    </div>

                    <!-- Imagen del Envase -->
                    <div>
                        <InputLabel value="Imagen del Envase" class="text-xs font-semibold mb-1.5" />
                        
                        <!-- Preview si hay imagen -->
                        <div v-if="imagePreview" class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200/80 rounded-xl mb-2.5">
                            <div class="w-14 h-14 rounded-lg bg-white border border-slate-200 p-1 flex items-center justify-center shrink-0 overflow-hidden">
                                <img :src="imagePreview" alt="Vista previa" class="max-h-full max-w-full object-contain" />
                            </div>
                            <div class="flex-1 min-w-0 text-xs">
                                <p class="font-bold text-slate-800 truncate">
                                    {{ presentationForm.image ? presentationForm.image.name : presentationForm.image_path }}
                                </p>
                                <p class="text-slate-400 text-[11px]">Imagen vinculada para la presentación</p>
                            </div>
                            <button
                                type="button"
                                @click="removeImage"
                                class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2.5 py-1 rounded-lg hover:bg-rose-50 transition"
                            >
                                Quitar
                            </button>
                        </div>

                        <!-- Selector de presets o subir archivo -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] text-slate-500 font-medium">Asignar imagen:</span>
                            <button
                                v-for="preset in presetImages"
                                :key="preset.path"
                                type="button"
                                @click="selectPresetImage(preset.path)"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-semibold border transition cursor-pointer flex items-center gap-1.5',
                                    presentationForm.image_path === preset.path
                                        ? 'bg-blue-50 text-blue-700 border-blue-300 ring-1 ring-blue-300'
                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <i class="fa-solid fa-bottle-water text-xs text-blue-500"></i>
                                <span>{{ preset.label }}</span>
                            </button>

                            <label
                                class="px-2.5 py-1 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 cursor-pointer flex items-center gap-1.5 transition"
                            >
                                <i class="fa-solid fa-cloud-arrow-up text-xs text-slate-500"></i>
                                <span>Subir archivo...</span>
                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    @change="handleImageUpload"
                                    class="hidden"
                                />
                            </label>
                        </div>
                        <InputError :message="presentationForm.errors.image || presentationForm.errors.image_path" class="mt-1" />
                    </div>

                    <!-- Precios (3 columnas con etiquetas claras) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                        <div>
                            <InputLabel for="pres_refill_price" value="A Domicilio (MXN)" class="text-xs font-semibold" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    $
                                </div>
                                <TextInput
                                    id="pres_refill_price"
                                    v-model="presentationForm.refill_price"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full text-xs pl-6"
                                    placeholder="Ej. 15.00"
                                />
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">Fijo o de cambio</p>
                            <InputError :message="presentationForm.errors.refill_price" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="pres_store_price" value="En Sucursal (MXN)" class="text-xs font-semibold" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    $
                                </div>
                                <TextInput
                                    id="pres_store_price"
                                    v-model="presentationForm.store_price"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full text-xs pl-6"
                                    placeholder="Ej. 10.00"
                                />
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">Relleno en planta</p>
                            <InputError :message="presentationForm.errors.store_price" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="pres_new_bottle_price" value="Envase Nuevo (MXN)" class="text-xs font-semibold" />
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs">
                                    $
                                </div>
                                <TextInput
                                    id="pres_new_bottle_price"
                                    v-model="presentationForm.new_bottle_price"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full text-xs pl-6"
                                    placeholder="Ej. 95.00"
                                />
                            </div>
                            <p class="text-[10px] text-slate-400 mt-0.5">Envase completo</p>
                            <InputError :message="presentationForm.errors.new_bottle_price" class="mt-1" />
                        </div>
                    </div>

                    <!-- Disponible a Domicilio -->
                    <div class="p-3.5 rounded-xl border" :class="presentationForm.is_available_for_delivery ? 'bg-blue-50/70 border-blue-200' : 'bg-slate-50 border-slate-200'">
                        <label class="inline-flex items-start gap-2.5 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="presentationForm.is_available_for_delivery"
                                class="rounded border-slate-300 text-blue-600 shadow-xs focus:ring-blue-500 mt-0.5"
                            />
                            <div>
                                <span class="text-xs font-bold text-slate-900 block">Disponible para pedidos a domicilio</span>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Los pedidos a domicilio son exclusivamente para botellones de 20 litros. Las presentaciones de 10 litros u otras capacidades son exclusivas para rellenado o venta en sucursal.
                                </p>
                            </div>
                        </label>
                        <InputError :message="presentationForm.errors.is_available_for_delivery" class="mt-1.5" />
                    </div>

                    <!-- Estado Inicial / Actual -->
                    <div class="pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="presentationForm.is_active"
                                class="rounded border-slate-300 text-blue-600 shadow-xs focus:ring-blue-500"
                            />
                            <span class="text-xs font-medium text-slate-700">Presentación activa para consultas del sistema</span>
                        </label>
                    </div>

                    <!-- Acciones del Modal -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <SecondaryButton
                            type="button"
                            @click="closePresentationModal"
                            :disabled="presentationForm.processing"
                        >
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="presentationForm.processing"
                            class="flex items-center gap-2"
                        >
                            <i v-if="presentationForm.processing" class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                            <span>{{ editingPresentation ? 'Guardar Cambios' : 'Registrar Envase' }}</span>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ========================================================================= -->
        <!-- MODAL: CONFIRMACIÓN DE ACTIVAR / DESACTIVAR PRESENTACIÓN -->
        <!-- ========================================================================= -->
        <Modal :show="isToggleStatusModalOpen" @close="closeToggleStatusModal" max-width="md">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                        :class="presentationToToggle?.is_active ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
                    >
                        <i :class="presentationToToggle?.is_active ? 'fa-solid fa-power-off' : 'fa-solid fa-check'"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            {{ presentationToToggle?.is_active ? '¿Desactivar presentación?' : '¿Activar presentación?' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ presentationToToggle?.name }} ({{ presentationToToggle?.formatted_capacity }})
                        </p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    {{
                        presentationToToggle?.is_active
                            ? 'Al desactivar esta presentación, no se ofrecerá para nuevos pedidos ni consultas activas. Los registros e historiales previos se conservarán intactos sin eliminación física.'
                            : 'Al activar esta presentación, volverá a estar disponible en el catálogo activo para consultas y asignaciones futuras.'
                    }}
                </p>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                    <SecondaryButton
                        type="button"
                        @click="closeToggleStatusModal"
                        :disabled="isTogglingStatus"
                    >
                        Cancelar
                    </SecondaryButton>
                    <PrimaryButton
                        v-if="!presentationToToggle?.is_active"
                        type="button"
                        @click="confirmToggleStatus"
                        :disabled="isTogglingStatus"
                        class="bg-emerald-600 hover:bg-emerald-700"
                    >
                        <i v-if="isTogglingStatus" class="fa-solid fa-circle-notch fa-spin text-xs mr-1"></i>
                        <span>Confirmar Activación</span>
                    </PrimaryButton>
                    <DangerButton
                        v-else
                        type="button"
                        @click="confirmToggleStatus"
                        :disabled="isTogglingStatus"
                    >
                        <i v-if="isTogglingStatus" class="fa-solid fa-circle-notch fa-spin text-xs mr-1"></i>
                        <span>Confirmar Desactivación</span>
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
