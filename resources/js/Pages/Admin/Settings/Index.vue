<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { toastError } from '@/utils/swal';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Coffee,
    Image as ImageIcon,
    Minus,
    Moon,
    PackagePlus,
    Plus,
    RotateCcw,
    Save,
    ShieldCheck,
    ShoppingBag,
    Store,
    Sunrise,
    Tag,
    Truck,
    Upload,
} from 'lucide-vue-next';

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

const breadcrumbs = [
    { label: 'Administración', href: route('dashboard') },
    { label: 'Configuración general' },
];

const getInitialTab = () => {
    if (typeof window !== 'undefined') {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (['prices', 'schedule', 'debt'].includes(tab)) return tab;
    }
    return 'prices';
};

const activeTab = ref(getInitialTab());

const selectTab = (tab) => {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
};

/* =========================================================================
   1. PRECIOS E IMAGEN DEL BOTELLÓN DE 20 L
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
    refill_price: currentBottle.value.refill_price ?? '',
    store_price: currentBottle.value.store_price ?? '',
    new_bottle_price: currentBottle.value.new_bottle_price ?? '',
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
    fileInputRef.value?.click();
};

const handleImageUpload = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        bottleForm.image = file;
        bottleForm.image_path = '';
        imagePreview.value = URL.createObjectURL(file);
    }
};

const resetBottleForm = () => {
    bottleForm.name = currentBottle.value.name || 'Botellón 20 Litros';
    bottleForm.refill_price = currentBottle.value.refill_price ?? '';
    bottleForm.store_price = currentBottle.value.store_price ?? '';
    bottleForm.new_bottle_price = currentBottle.value.new_bottle_price ?? '';
    bottleForm.image_path = currentBottle.value.image_path || '/images/garrafon-20l.png';
    bottleForm.image = null;
    bottleForm.is_available_for_delivery = Boolean(currentBottle.value.is_available_for_delivery);
    bottleForm.clearErrors();
    imagePreview.value = currentBottle.value.image_url || currentBottle.value.image_path || '/images/garrafon-20l.png';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitBottleForm = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            bottleForm.image = null;
            bottleForm.image_path = currentBottle.value.image_path || '/images/garrafon-20l.png';
            imagePreview.value = currentBottle.value.image_url || currentBottle.value.image_path || '/images/garrafon-20l.png';
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        },
        onError: () => {
            toastError('Revisa los precios o la imagen antes de guardar.');
        },
    };

    if (bottleForm.image) {
        bottleForm
            .transform((data) => ({ ...data, _method: 'PUT' }))
            .post(route('admin.settings.bottle-presentations.update', currentBottle.value.id), options);
    } else {
        bottleForm.put(route('admin.settings.bottle-presentations.update', currentBottle.value.id), options);
    }
};

const isPriceFormDirty = computed(() => {
    const origRefill = currentBottle.value.refill_price !== null && currentBottle.value.refill_price !== undefined ? String(currentBottle.value.refill_price) : '';
    const origStore = currentBottle.value.store_price !== null && currentBottle.value.store_price !== undefined ? String(currentBottle.value.store_price) : '';
    const origNew = currentBottle.value.new_bottle_price !== null && currentBottle.value.new_bottle_price !== undefined ? String(currentBottle.value.new_bottle_price) : '';
    const origDelivery = Boolean(currentBottle.value.is_available_for_delivery);
    const origImg = currentBottle.value.image_path || '/images/garrafon-20l.png';

    return (
        String(bottleForm.refill_price ?? '') !== origRefill ||
        String(bottleForm.store_price ?? '') !== origStore ||
        String(bottleForm.new_bottle_price ?? '') !== origNew ||
        Boolean(bottleForm.is_available_for_delivery) !== origDelivery ||
        Boolean(bottleForm.image) ||
        (Boolean(bottleForm.image_path) && bottleForm.image_path !== origImg)
    );
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

const cutoffQuickOptions = [30, 45, 60, 90];

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

const formatDuration = (minutes) => {
    if (!minutes || minutes <= 0) return '0 min';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h > 0 && m > 0) return `${h} h ${m} min`;
    if (h > 0) return `${h} h`;
    return `${m} min`;
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
        return { isValid: false, errors, cutoffTime: '--:--', segments: [] };
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
    const totalSpan = Math.max(1, closeMin - openMin);
    const morningDuration = Math.max(0, breakStartMin - openMin);
    const breakDuration = Math.max(0, breakEndMin - breakStartMin);
    const afternoonOrderDuration = Math.max(0, cutoffMin - breakEndMin);
    const prepTailDuration = Math.max(0, closeMin - cutoffMin);

    return {
        isValid,
        errors,
        cutoffTime,
        breakDurationText: formatDuration(breakDuration),
        segments: isValid
            ? [
                  {
                      key: 'morning',
                      label: 'Mañana',
                      pct: (morningDuration / totalSpan) * 100,
                      colorClass: 'bg-[#0071BA]',
                  },
                  {
                      key: 'break',
                      label: 'Descanso',
                      pct: (breakDuration / totalSpan) * 100,
                      colorClass: 'bg-[#D99A27]',
                  },
                  {
                      key: 'afternoon',
                      label: 'Pedidos tarde',
                      pct: (afternoonOrderDuration / totalSpan) * 100,
                      colorClass: 'bg-[#26845B]',
                  },
                  {
                      key: 'prep',
                      label: 'Solo entregas',
                      pct: (prepTailDuration / totalSpan) * 100,
                      colorClass: 'bg-[#1E1545]',
                  },
              ].filter((s) => s.pct > 0)
            : [],
    };
});

const isScheduleFormDirty = computed(() => {
    return (
        scheduleForm.opening_time !== (props.schedule.opening_time || '08:00') ||
        scheduleForm.break_start_time !== (props.schedule.break_start_time || '13:00') ||
        scheduleForm.break_end_time !== (props.schedule.break_end_time || '15:00') ||
        scheduleForm.closing_time !== (props.schedule.closing_time || '19:00') ||
        Number(scheduleForm.order_cutoff_advance_minutes) !== Number(props.schedule.order_cutoff_advance_minutes ?? 60)
    );
});

const resetScheduleForm = () => {
    scheduleForm.opening_time = props.schedule.opening_time || '08:00';
    scheduleForm.break_start_time = props.schedule.break_start_time || '13:00';
    scheduleForm.break_end_time = props.schedule.break_end_time || '15:00';
    scheduleForm.closing_time = props.schedule.closing_time || '19:00';
    scheduleForm.order_cutoff_advance_minutes = props.schedule.order_cutoff_advance_minutes ?? 60;
    scheduleForm.clearErrors();
};

const submitSchedule = () => {
    scheduleForm.put(route('admin.settings.schedule.update'), {
        preserveScroll: true,
        onError: () => {
            toastError('Verifica las horas ingresadas antes de guardar.');
        },
    });
};

/* =========================================================================
   3. LÍMITE DE FIADO
   ========================================================================= */
const debtLimitForm = useForm({
    max_pending_payment_bottles: props.max_pending_payment_bottles ?? 5,
});

const debtQuickOptions = [0, 3, 5, 8, 10];

const adjustDebtLimit = (delta) => {
    const current = parseInt(debtLimitForm.max_pending_payment_bottles, 10) || 0;
    debtLimitForm.max_pending_payment_bottles = Math.max(0, current + delta);
};

const isDebtFormDirty = computed(() => {
    return Number(debtLimitForm.max_pending_payment_bottles) !== Number(props.max_pending_payment_bottles ?? 5);
});

const debtPolicyMeta = computed(() => {
    const val = parseInt(debtLimitForm.max_pending_payment_bottles, 10);
    if (isNaN(val) || val <= 0) {
        return {
            label: 'Contado estricto',
            badgeClass: 'bg-[#FCECEF] text-[#B91C3C] border-[#F6C8D2]',
            boxClass: 'bg-[#FCECEF]/50 border-[#F6C8D2]',
            description: 'Todo pedido se cobra al entregar; sin adeudos permitidos.',
        };
    }
    if (val <= 3) {
        return {
            label: 'Conservador',
            badgeClass: 'bg-[#FEF5E6] text-[#9A670E] border-[#F5DFB5]',
            boxClass: 'bg-[#FEF5E6]/60 border-[#F5DFB5]',
            description: `Hasta ${val} ${val === 1 ? 'botellón fiado' : 'botellones fiados'} por domicilio.`,
        };
    }
    if (val <= 6) {
        return {
            label: 'Estándar',
            badgeClass: 'bg-[#E8F2EC] text-[#26845B] border-[#BBE4CE]',
            boxClass: 'bg-[#E8F2EC]/60 border-[#BBE4CE]',
            description: `Hasta ${val} botellones pendientes por cliente sin frenar entregas.`,
        };
    }
    return {
        label: 'Amplio',
        badgeClass: 'bg-[#E9F3F8] text-[#0071BA] border-[#BDDDF0]',
        boxClass: 'bg-[#E9F3F8]/70 border-[#BDDDF0]',
        description: `Tope extendido de ${val} botellones pendientes para alto consumo.`,
    };
});

const resetDebtLimitForm = () => {
    debtLimitForm.max_pending_payment_bottles = props.max_pending_payment_bottles ?? 5;
    debtLimitForm.clearErrors();
};

const submitDebtLimit = () => {
    debtLimitForm.put(route('admin.settings.debt-limit.update'), {
        preserveScroll: true,
        onError: () => {
            toastError('Ingresa un número válido igual o mayor a 0.');
        },
    });
};

watch(
    () => props.schedule,
    (next) => {
        if (!next) return;
        scheduleForm.opening_time = next.opening_time || '08:00';
        scheduleForm.break_start_time = next.break_start_time || '13:00';
        scheduleForm.break_end_time = next.break_end_time || '15:00';
        scheduleForm.closing_time = next.closing_time || '19:00';
        scheduleForm.order_cutoff_advance_minutes = next.order_cutoff_advance_minutes ?? 60;
    },
    { deep: true }
);

watch(
    () => props.max_pending_payment_bottles,
    (next) => {
        debtLimitForm.max_pending_payment_bottles = next ?? 5;
    }
);

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

    <AuthenticatedLayout :wide="true">
        <div class="py-5 sm:py-7 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto space-y-5">
            <!-- Encabezado compacto -->
            <div>
                <Breadcrumbs :items="breadcrumbs" />
                <h1 class="mt-2 text-2xl sm:text-[26px] font-bold text-[#292638] tracking-tight">
                    Configuración general
                </h1>
                <p class="text-xs sm:text-sm text-[#747181] mt-0.5">
                    Tarifas de 20 L, horario operativo y límite de fiado.
                </p>
            </div>

            <!-- 3 tarjetas selectoras compactas y coloridas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3" role="tablist" aria-label="Secciones de configuración">
                <!-- Tab 1: Precios -->
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'prices'"
                    @click="selectTab('prices')"
                    class="group text-left p-4 rounded-2xl border transition-all cursor-pointer flex items-center gap-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]"
                    :class="activeTab === 'prices'
                        ? 'bg-white border-[#0071BA] ring-1 ring-[#0071BA]/30 shadow-[0_6px_18px_rgba(0,113,186,0.08)]'
                        : 'bg-white/90 border-[#E9E7F0] hover:bg-white hover:border-[#BDDDF0]'"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                        :class="activeTab === 'prices' ? 'bg-[#0071BA] text-white' : 'bg-[#E9F3F8] text-[#0071BA]'"
                    >
                        <Tag class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold text-[#292638] truncate">
                                Tarifas y 20 L
                            </h2>
                            <span v-if="isPriceFormDirty" class="w-2 h-2 rounded-full bg-[#E82147] shrink-0" title="Cambios sin guardar" />
                        </div>
                        <p class="text-xs text-[#747181] tabular-nums truncate mt-0.5">
                            {{ formatCurrency(bottleForm.store_price) || '—' }} planta · {{ formatCurrency(bottleForm.refill_price) || '—' }} ruta
                        </p>
                    </div>
                </button>

                <!-- Tab 2: Horarios -->
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'schedule'"
                    @click="selectTab('schedule')"
                    class="group text-left p-4 rounded-2xl border transition-all cursor-pointer flex items-center gap-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]"
                    :class="activeTab === 'schedule'
                        ? 'bg-white border-[#1E1545] ring-1 ring-[#1E1545]/25 shadow-[0_6px_18px_rgba(30,21,69,0.08)]'
                        : 'bg-white/90 border-[#E9E7F0] hover:bg-white hover:border-[#CBC4DD]'"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                        :class="activeTab === 'schedule' ? 'bg-[#1E1545] text-white' : 'bg-[#EEEAF7] text-[#1E1545]'"
                    >
                        <Clock class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold text-[#292638] truncate">
                                Horario
                            </h2>
                            <span v-if="isScheduleFormDirty" class="w-2 h-2 rounded-full bg-[#E82147] shrink-0" title="Cambios sin guardar" />
                        </div>
                        <p class="text-xs text-[#747181] tabular-nums truncate mt-0.5">
                            {{ scheduleForm.opening_time }} – {{ scheduleForm.closing_time }} · Corte {{ scheduleAnalysis.cutoffTime }}
                        </p>
                    </div>
                </button>

                <!-- Tab 3: Fiado -->
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'debt'"
                    @click="selectTab('debt')"
                    class="group text-left p-4 rounded-2xl border transition-all cursor-pointer flex items-center gap-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]"
                    :class="activeTab === 'debt'
                        ? 'bg-white border-[#26845B] ring-1 ring-[#26845B]/25 shadow-[0_6px_18px_rgba(38,132,91,0.08)]'
                        : 'bg-white/90 border-[#E9E7F0] hover:bg-white hover:border-[#BBE4CE]'"
                >
                    <div
                        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                        :class="activeTab === 'debt' ? 'bg-[#26845B] text-white' : 'bg-[#E8F2EC] text-[#26845B]'"
                    >
                        <ShieldCheck class="w-4 h-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold text-[#292638] truncate">
                                Límite de fiado
                            </h2>
                            <span v-if="isDebtFormDirty" class="w-2 h-2 rounded-full bg-[#E82147] shrink-0" title="Cambios sin guardar" />
                        </div>
                        <p class="text-xs text-[#747181] tabular-nums truncate mt-0.5">
                            Máximo {{ debtLimitForm.max_pending_payment_bottles }} {{ Number(debtLimitForm.max_pending_payment_bottles) === 1 ? 'botellón' : 'botellones' }}
                        </p>
                    </div>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. TARIFAS Y PRODUCTO 20 L -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'prices'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Formulario principal -->
                <form
                    @submit.prevent="submitBottleForm"
                    class="lg:col-span-8 bg-white border border-[#E9E7F0] border-t-4 border-t-[#0071BA] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden"
                >
                    <div class="p-5 sm:p-6 space-y-5">
                        <!-- Precios -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="store_price" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638] mb-1.5">
                                    <Store class="w-3.5 h-3.5 text-[#26845B]" />
                                    En planta
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#747181] text-sm font-semibold">$</span>
                                    <TextInput
                                        id="store_price"
                                        v-model="bottleForm.store_price"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full pl-7 pr-12 text-sm font-bold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#0071BA] focus:ring-[#0071BA]/15 rounded-xl min-h-[42px]"
                                        placeholder="10.00"
                                        required
                                    />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-[#747181] text-[11px] font-medium">MXN</span>
                                </div>
                                <InputError :message="bottleForm.errors.store_price" class="mt-1" />
                            </div>

                            <div>
                                <label for="refill_price" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638] mb-1.5">
                                    <Truck class="w-3.5 h-3.5 text-[#0071BA]" />
                                    A domicilio
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#747181] text-sm font-semibold">$</span>
                                    <TextInput
                                        id="refill_price"
                                        v-model="bottleForm.refill_price"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full pl-7 pr-12 text-sm font-bold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#0071BA] focus:ring-[#0071BA]/15 rounded-xl min-h-[42px]"
                                        placeholder="15.00"
                                        required
                                    />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-[#747181] text-[11px] font-medium">MXN</span>
                                </div>
                                <InputError :message="bottleForm.errors.refill_price" class="mt-1" />
                            </div>

                            <div>
                                <label for="new_bottle_price" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638] mb-1.5">
                                    <PackagePlus class="w-3.5 h-3.5 text-[#1E1545]" />
                                    Envase nuevo
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#747181] text-sm font-semibold">$</span>
                                    <TextInput
                                        id="new_bottle_price"
                                        v-model="bottleForm.new_bottle_price"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full pl-7 pr-12 text-sm font-bold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                        placeholder="95.00"
                                    />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-[#747181] text-[11px] font-medium">MXN</span>
                                </div>
                                <InputError :message="bottleForm.errors.new_bottle_price" class="mt-1" />
                            </div>
                        </div>

                        <!-- Entrega a domicilio -->
                        <div
                            class="px-4 py-3.5 rounded-xl border transition-colors flex items-center justify-between gap-4"
                            :class="bottleForm.is_available_for_delivery ? 'bg-[#E9F3F8]/65 border-[#BDDDF0]' : 'bg-[#F4F3F8] border-[#E9E7F0]'"
                        >
                            <div>
                                <p class="text-sm font-semibold text-[#292638]">
                                    Entrega a domicilio
                                </p>
                                <p class="text-xs text-[#747181]">
                                    {{ bottleForm.is_available_for_delivery ? 'Activa para pedidos en ruta y planta.' : 'Pausada — solo recargas en sucursal.' }}
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="bottleForm.is_available_for_delivery"
                                @click="bottleForm.is_available_for_delivery = !bottleForm.is_available_for_delivery"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1E1545]"
                                :class="bottleForm.is_available_for_delivery ? 'bg-[#1E1545]' : 'bg-[#CBC4DD]'"
                                title="Alternar disponibilidad a domicilio"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition duration-200"
                                    :class="bottleForm.is_available_for_delivery ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </div>

                        <!-- Imagen -->
                        <div class="pt-4 border-t border-[#E9E7F0] flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold text-[#292638]">Imagen pública</p>
                                <p class="text-[11px] text-[#747181]">Mostrada en catálogo y pedidos.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="useStandardImage"
                                    class="px-3 py-2 rounded-xl text-xs font-medium border transition cursor-pointer inline-flex items-center gap-1.5 min-h-[38px]"
                                    :class="bottleForm.image_path === '/images/garrafon-20l.png' && !bottleForm.image
                                        ? 'bg-[#EEEAF7] text-[#1E1545] border-[#CBC4DD]'
                                        : 'bg-white text-[#747181] border-[#E9E7F0] hover:bg-[#F4F3F8] hover:text-[#292638]'"
                                >
                                    <ImageIcon class="w-3.5 h-3.5" />
                                    Estándar
                                </button>

                                <button
                                    type="button"
                                    @click="triggerImageUpload"
                                    class="px-3 py-2 rounded-xl text-xs font-medium border border-[#E9E7F0] bg-white hover:bg-[#E9F3F8]/60 text-[#292638] cursor-pointer min-h-[38px] transition inline-flex items-center gap-1.5"
                                >
                                    <Upload class="w-3.5 h-3.5 text-[#0071BA]" />
                                    Subir imagen
                                </button>

                                <input
                                    ref="fileInputRef"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    @change="handleImageUpload"
                                    class="hidden"
                                />
                            </div>
                            <InputError :message="bottleForm.errors.image || bottleForm.errors.image_path" class="w-full" />
                        </div>
                    </div>

                    <!-- Pie -->
                    <div class="px-5 sm:px-6 py-3.5 bg-[#F4F3F8]/75 border-t border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs inline-flex items-center gap-1.5" :class="isPriceFormDirty ? 'text-[#E82147] font-semibold' : 'text-[#26845B] font-medium'">
                            <span v-if="isPriceFormDirty" class="w-2 h-2 rounded-full bg-[#E82147]" />
                            <CheckCircle2 v-else class="w-3.5 h-3.5" />
                            {{ isPriceFormDirty ? 'Cambios sin guardar' : 'Tarifas al día' }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="isPriceFormDirty"
                                type="button"
                                @click="resetBottleForm"
                                class="px-3 py-2 rounded-xl text-xs font-medium border border-[#E9E7F0] bg-white text-[#747181] hover:text-[#292638] transition cursor-pointer inline-flex items-center gap-1.5 min-h-[38px]"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                                Restablecer
                            </button>

                            <button
                                type="submit"
                                :disabled="bottleForm.processing"
                                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-[#1E1545] hover:bg-[#2B1F60] disabled:opacity-60 transition cursor-pointer inline-flex items-center gap-2 min-h-[38px]"
                            >
                                <Save class="w-3.5 h-3.5" />
                                {{ bottleForm.processing ? 'Guardando...' : 'Guardar tarifas' }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Ficha compacta 20 L -->
                <div class="lg:col-span-4 bg-white border border-[#E9E7F0] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden">
                    <div class="bg-[#E9F3F8]/70 px-5 py-5 flex flex-col items-center text-center relative">
                        <span
                            class="self-end inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold mb-2"
                            :class="bottleForm.is_available_for_delivery ? 'bg-white text-[#0071BA] border border-[#BDDDF0]' : 'bg-[#FEF5E6] text-[#9A670E] border border-[#F5DFB5]'"
                        >
                            {{ bottleForm.is_available_for_delivery ? 'Planta y domicilio' : 'Solo planta' }}
                        </span>
                        <div class="w-24 h-24 rounded-full bg-white border border-[#E9E7F0] flex items-center justify-center p-2.5 shadow-2xs">
                            <img :src="imagePreview" alt="Botellón 20 L" class="max-h-full max-w-full object-contain" />
                        </div>
                        <h3 class="mt-2.5 text-sm font-bold text-[#292638]">Botellón 20 L</h3>
                    </div>

                    <div class="p-4 divide-y divide-[#E9E7F0] text-xs">
                        <div class="py-2 flex items-center justify-between">
                            <span class="text-[#747181]">Planta</span>
                            <span class="font-bold text-[#26845B] tabular-nums">{{ formatCurrency(bottleForm.store_price) || '—' }}</span>
                        </div>
                        <div class="py-2 flex items-center justify-between">
                            <span class="text-[#747181]">Domicilio</span>
                            <span class="font-bold text-[#0071BA] tabular-nums">{{ formatCurrency(bottleForm.refill_price) || '—' }}</span>
                        </div>
                        <div class="pt-2 flex items-center justify-between">
                            <span class="text-[#747181]">Envase nuevo</span>
                            <span class="font-bold text-[#1E1545] tabular-nums">{{ formatCurrency(bottleForm.new_bottle_price) || '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. HORARIO DE LA SUCURSAL -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'schedule'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Editor -->
                <form
                    @submit.prevent="submitSchedule"
                    class="lg:col-span-7 bg-white border border-[#E9E7F0] border-t-4 border-t-[#1E1545] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden"
                >
                    <div class="p-5 sm:p-6 space-y-5">
                        <!-- Apertura y cierre -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="opening_time" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638] mb-1.5">
                                    <Sunrise class="w-3.5 h-3.5 text-[#D99A27]" />
                                    Apertura
                                </label>
                                <TextInput
                                    id="opening_time"
                                    v-model="scheduleForm.opening_time"
                                    type="time"
                                    class="w-full text-sm font-semibold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                    required
                                />
                                <InputError :message="scheduleForm.errors.opening_time || scheduleAnalysis.errors.opening_time" class="mt-1" />
                            </div>

                            <div>
                                <label for="closing_time" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638] mb-1.5">
                                    <Moon class="w-3.5 h-3.5 text-[#1E1545]" />
                                    Cierre
                                </label>
                                <TextInput
                                    id="closing_time"
                                    v-model="scheduleForm.closing_time"
                                    type="time"
                                    class="w-full text-sm font-semibold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                    required
                                />
                                <InputError :message="scheduleForm.errors.closing_time || scheduleAnalysis.errors.closing_time" class="mt-1" />
                            </div>
                        </div>

                        <!-- Descanso -->
                        <div class="pt-4 border-t border-[#E9E7F0] space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#292638]">
                                    <Coffee class="w-3.5 h-3.5 text-[#D99A27]" />
                                    Descanso
                                </span>
                                <span v-if="scheduleAnalysis.isValid" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#FEF5E6] text-[#9A670E]">
                                    {{ scheduleAnalysis.breakDurationText }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="break_start_time" class="block text-xs font-medium text-[#747181] mb-1.5">Inicio</label>
                                    <TextInput
                                        id="break_start_time"
                                        v-model="scheduleForm.break_start_time"
                                        type="time"
                                        class="w-full text-sm font-semibold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.break_start_time || scheduleAnalysis.errors.break_start_time" class="mt-1" />
                                </div>

                                <div>
                                    <label for="break_end_time" class="block text-xs font-medium text-[#747181] mb-1.5">Fin</label>
                                    <TextInput
                                        id="break_end_time"
                                        v-model="scheduleForm.break_end_time"
                                        type="time"
                                        class="w-full text-sm font-semibold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                        required
                                    />
                                    <InputError :message="scheduleForm.errors.break_end_time || scheduleAnalysis.errors.break_end_time" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- Corte de pedidos -->
                        <div class="pt-4 border-t border-[#E9E7F0] space-y-2.5">
                            <label for="order_cutoff_advance_minutes" class="flex items-center gap-1.5 text-xs font-semibold text-[#292638]">
                                <ShoppingBag class="w-3.5 h-3.5 text-[#0071BA]" />
                                Corte antes del cierre
                            </label>

                            <div class="flex flex-wrap items-center gap-2.5">
                                <div class="relative w-32">
                                    <TextInput
                                        id="order_cutoff_advance_minutes"
                                        v-model="scheduleForm.order_cutoff_advance_minutes"
                                        type="number"
                                        min="0"
                                        max="1440"
                                        class="w-full pl-3.5 pr-11 text-sm font-bold text-[#292638] tabular-nums border-[#E9E7F0] focus:border-[#1E1545] focus:ring-[#1E1545]/15 rounded-xl min-h-[42px]"
                                        required
                                    />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-[#747181] text-xs">min</span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button
                                        v-for="mins in cutoffQuickOptions"
                                        :key="mins"
                                        type="button"
                                        @click="scheduleForm.order_cutoff_advance_minutes = mins"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium border transition cursor-pointer"
                                        :class="Number(scheduleForm.order_cutoff_advance_minutes) === mins
                                            ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                            : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                    >
                                        {{ mins }} min
                                    </button>
                                </div>
                            </div>
                            <InputError :message="scheduleForm.errors.order_cutoff_advance_minutes || scheduleAnalysis.errors.order_cutoff_advance_minutes" />
                        </div>
                    </div>

                    <!-- Pie -->
                    <div class="px-5 sm:px-6 py-3.5 bg-[#F4F3F8]/75 border-t border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                        <span
                            class="text-xs inline-flex items-center gap-1.5"
                            :class="!scheduleAnalysis.isValid ? 'text-[#E82147] font-semibold' : (isScheduleFormDirty ? 'text-[#E82147] font-semibold' : 'text-[#26845B] font-medium')"
                        >
                            <AlertTriangle v-if="!scheduleAnalysis.isValid" class="w-3.5 h-3.5" />
                            <span v-else-if="isScheduleFormDirty" class="w-2 h-2 rounded-full bg-[#E82147]" />
                            <CheckCircle2 v-else class="w-3.5 h-3.5" />
                            {{ !scheduleAnalysis.isValid ? 'Horario inválido' : (isScheduleFormDirty ? 'Cambios sin guardar' : 'Horario vigente') }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="isScheduleFormDirty"
                                type="button"
                                @click="resetScheduleForm"
                                class="px-3 py-2 rounded-xl text-xs font-medium border border-[#E9E7F0] bg-white text-[#747181] hover:text-[#292638] transition cursor-pointer inline-flex items-center gap-1.5 min-h-[38px]"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                                Restablecer
                            </button>

                            <button
                                type="submit"
                                :disabled="scheduleForm.processing || !scheduleAnalysis.isValid"
                                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-[#1E1545] hover:bg-[#2B1F60] disabled:opacity-50 transition cursor-pointer inline-flex items-center gap-2 min-h-[38px]"
                            >
                                <Save class="w-3.5 h-3.5" />
                                {{ scheduleForm.processing ? 'Guardando...' : 'Guardar horario' }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Línea de tiempo compacta -->
                <div class="lg:col-span-5 bg-white border border-[#E9E7F0] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] p-5 space-y-4">
                    <div
                        v-if="!scheduleAnalysis.isValid"
                        class="bg-[#FCECEF] border border-[#F6C8D2] rounded-xl p-4 text-[#B91C3C] space-y-1.5 text-xs"
                    >
                        <p class="font-bold flex items-center gap-1.5">
                            <AlertTriangle class="w-4 h-4 shrink-0" />
                            Ajusta las franjas horarias
                        </p>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li v-for="(err, key) in scheduleAnalysis.errors" :key="key">{{ err }}</li>
                        </ul>
                    </div>

                    <template v-else>
                        <div class="p-3.5 rounded-xl bg-[#E9F3F8] border border-[#BDDDF0] flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-[#0071BA] text-white flex items-center justify-center shrink-0">
                                <ShoppingBag class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-sm font-bold text-[#1E1545] tabular-nums">
                                    Pedidos hasta las {{ scheduleAnalysis.cutoffTime }}
                                </p>
                                <p class="text-[11px] text-[#0071BA]">Lunes a domingo</p>
                            </div>
                        </div>

                        <div class="space-y-2.5 pt-1">
                            <div class="flex justify-between text-[11px] font-semibold text-[#747181] tabular-nums">
                                <span>{{ scheduleForm.opening_time }}</span>
                                <span>{{ scheduleForm.break_start_time }}–{{ scheduleForm.break_end_time }}</span>
                                <span>{{ scheduleForm.closing_time }}</span>
                            </div>

                            <div class="h-3 w-full rounded-full overflow-hidden flex bg-[#F4F3F8] gap-0.5 p-0.5 border border-[#E9E7F0]">
                                <div
                                    v-for="seg in scheduleAnalysis.segments"
                                    :key="seg.key"
                                    :style="{ width: `${seg.pct}%` }"
                                    :title="seg.label"
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="seg.colorClass"
                                />
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[#747181]">
                                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#0071BA]" />Mañana</span>
                                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#D99A27]" />Descanso</span>
                                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#26845B]" />Pedidos tarde</span>
                                <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#1E1545]" />Entregas</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 3. LÍMITE DE FIADO -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'debt'" class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Editor -->
                <form
                    @submit.prevent="submitDebtLimit"
                    class="lg:col-span-7 bg-white border border-[#E9E7F0] border-t-4 border-t-[#26845B] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] overflow-hidden"
                >
                    <div class="p-5 sm:p-6 space-y-5">
                        <div class="space-y-3">
                            <label for="debt_limit" class="block text-xs font-semibold text-[#292638]">
                                Máximo de botellones pendientes por cliente
                            </label>

                            <div class="flex flex-wrap items-center gap-3">
                                <div class="inline-flex items-center rounded-xl border border-[#E9E7F0] bg-[#F4F3F8] p-1 gap-1">
                                    <button
                                        type="button"
                                        @click="adjustDebtLimit(-1)"
                                        :disabled="Number(debtLimitForm.max_pending_payment_bottles) <= 0"
                                        class="w-10 h-10 rounded-lg bg-white text-[#292638] border border-[#E9E7F0] hover:bg-[#EEEAF7] disabled:opacity-40 transition cursor-pointer inline-flex items-center justify-center"
                                        title="Disminuir en 1"
                                    >
                                        <Minus class="w-4 h-4" />
                                    </button>

                                    <TextInput
                                        id="debt_limit"
                                        v-model="debtLimitForm.max_pending_payment_bottles"
                                        type="number"
                                        min="0"
                                        class="w-24 text-center text-lg font-extrabold text-[#1E1545] tabular-nums bg-white border-[#E9E7F0] focus:border-[#26845B] focus:ring-[#26845B]/15 rounded-lg min-h-[40px]"
                                        required
                                    />

                                    <button
                                        type="button"
                                        @click="adjustDebtLimit(1)"
                                        class="w-10 h-10 rounded-lg bg-white text-[#292638] border border-[#E9E7F0] hover:bg-[#EEEAF7] transition cursor-pointer inline-flex items-center justify-center"
                                        title="Aumentar en 1"
                                    >
                                        <Plus class="w-4 h-4" />
                                    </button>
                                </div>

                                <span class="text-xs font-medium text-[#747181]">
                                    {{ Number(debtLimitForm.max_pending_payment_bottles) === 1 ? 'botellón de 20 L' : 'botellones de 20 L' }}
                                </span>
                            </div>

                            <!-- Valores rápidos -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    v-for="preset in debtQuickOptions"
                                    :key="preset"
                                    type="button"
                                    @click="debtLimitForm.max_pending_payment_bottles = preset"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition cursor-pointer"
                                    :class="Number(debtLimitForm.max_pending_payment_bottles) === preset
                                        ? 'bg-[#1E1545] text-white border-[#1E1545]'
                                        : 'bg-white text-[#747181] border-[#E9E7F0] hover:text-[#292638]'"
                                >
                                    {{ preset === 0 ? 'Sin fiado (0)' : preset }}
                                </button>
                            </div>

                            <InputError :message="debtLimitForm.errors.max_pending_payment_bottles" />
                        </div>

                        <p class="text-xs text-[#747181] bg-[#F4F3F8] border border-[#E9E7F0] rounded-xl px-4 py-3">
                            Al llegar a este límite, el cliente podrá seguir recibiendo pedidos si paga en la entrega.
                        </p>
                    </div>

                    <!-- Pie -->
                    <div class="px-5 sm:px-6 py-3.5 bg-[#F4F3F8]/75 border-t border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                        <span class="text-xs inline-flex items-center gap-1.5" :class="isDebtFormDirty ? 'text-[#E82147] font-semibold' : 'text-[#26845B] font-medium'">
                            <span v-if="isDebtFormDirty" class="w-2 h-2 rounded-full bg-[#E82147]" />
                            <CheckCircle2 v-else class="w-3.5 h-3.5" />
                            {{ isDebtFormDirty ? 'Cambios sin guardar' : 'Política vigente' }}
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                v-if="isDebtFormDirty"
                                type="button"
                                @click="resetDebtLimitForm"
                                class="px-3 py-2 rounded-xl text-xs font-medium border border-[#E9E7F0] bg-white text-[#747181] hover:text-[#292638] transition cursor-pointer inline-flex items-center gap-1.5 min-h-[38px]"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                                Restablecer
                            </button>

                            <button
                                type="submit"
                                :disabled="debtLimitForm.processing"
                                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold text-white bg-[#1E1545] hover:bg-[#2B1F60] disabled:opacity-60 transition cursor-pointer inline-flex items-center gap-2 min-h-[38px]"
                            >
                                <Save class="w-3.5 h-3.5" />
                                {{ debtLimitForm.processing ? 'Guardando...' : 'Guardar límite' }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Resumen compacto -->
                <div class="lg:col-span-5 bg-white border border-[#E9E7F0] rounded-2xl shadow-[0_1px_2px_rgba(30,21,69,0.04)] p-5 space-y-3">
                    <div class="p-4 rounded-xl border flex items-center justify-between gap-3" :class="debtPolicyMeta.boxClass">
                        <div>
                            <span class="text-xs text-[#747181] block">Tope actual</span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-3xl font-black text-[#1E1545] tabular-nums">
                                    {{ debtLimitForm.max_pending_payment_bottles || 0 }}
                                </span>
                                <span class="text-xs font-semibold text-[#292638]">
                                    {{ Number(debtLimitForm.max_pending_payment_bottles) === 1 ? 'botellón' : 'botellones' }}
                                </span>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-full text-xs font-bold border shrink-0" :class="debtPolicyMeta.badgeClass">
                            {{ debtPolicyMeta.label }}
                        </span>
                    </div>

                    <p class="text-xs text-[#747181] leading-relaxed">
                        {{ debtPolicyMeta.description }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
