<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    hasActiveRoute: {
        type: Boolean,
        default: false,
    },
    createdCustomer: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const newlyCreated = ref(props.createdCustomer || null);

const availableZones = [
    { id: 'north', label: 'Norte' },
    { id: 'south', label: 'Sur' },
    { id: 'east', label: 'Este' },
    { id: 'west', label: 'Oeste' },
];

const bottleType = ref('none'); // 'none', 'exchange', 'fixed'
const bottlesCount = ref(1);

const form = useForm({
    nickname: '',
    name: '',
    delivery_zone: '',
    bottle_type: 'none',
    bottles_count: 0,
    fixed_bottles_count: 0,
    exchange_bottles_count: 0,
});

const selectType = (type) => {
    bottleType.value = type;
    form.bottle_type = type;
    if (type === 'none') {
        bottlesCount.value = 0;
    } else if (bottlesCount.value === 0) {
        bottlesCount.value = 1;
    }
    syncCounts();
};

const syncCounts = () => {
    const qty = Math.max(0, parseInt(bottlesCount.value, 10) || 0);
    form.bottles_count = qty;
    if (bottleType.value === 'fixed') {
        form.fixed_bottles_count = qty;
        form.exchange_bottles_count = 0;
    } else if (bottleType.value === 'exchange') {
        form.fixed_bottles_count = 0;
        form.exchange_bottles_count = qty;
    } else {
        form.fixed_bottles_count = 0;
        form.exchange_bottles_count = 0;
    }
};

const submitCustomer = () => {
    if (!form.nickname.trim()) {
        return;
    }
    if (!form.delivery_zone) {
        alert('Por favor especifica la zona de reparto (Norte, Sur, Este u Oeste).');
        return;
    }
    syncCounts();
    form.post(route('repartidor.customers.store-quick'), {
        preserveScroll: true,
        onSuccess: (pageRes) => {
            if (pageRes.props.flash?.createdCustomer) {
                newlyCreated.value = pageRes.props.flash.createdCustomer;
            } else if (page.props.createdCustomer) {
                newlyCreated.value = page.props.createdCustomer;
            }
            form.reset();
            bottleType.value = 'none';
            bottlesCount.value = 1;
        },
    });
};

const registerAnother = () => {
    newlyCreated.value = null;
    form.reset();
    form.clearErrors();
    bottleType.value = 'none';
    bottlesCount.value = 1;
};
</script>

<template>
    <Head title="Agregar Cliente - Reparto Móvil" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('repartidor.route.index')"
                        class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50 shadow-2xs transition"
                        title="Volver a Mi Ruta"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                    </Link>
                    <div>
                        <h2 class="text-xl font-bold leading-tight text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-user-plus text-blue-900"></i> Agregar Cliente en Ruta
                        </h2>
                        <p class="text-xs text-slate-500">Alta rápida sin trámites pesados</p>
                    </div>
                </div>

                <Link
                    :href="route('repartidor.dashboard')"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                >
                    Panel
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8 max-w-xl mx-auto px-4 sm:px-6 space-y-5">

            <!-- Mensaje de éxito si existe flash -->
            <div
                v-if="flashSuccess"
                class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm flex items-start gap-3 shadow-2xs"
            >
                <i class="fa-solid fa-circle-check text-lg text-emerald-600 mt-0.5 shrink-0"></i>
                <div class="flex-1 font-medium">{{ flashSuccess }}</div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. CASO: CLIENTE RECIÉN GUARDADO (OFRECER REGISTRAR SERVICIO)              -->
            <!-- ========================================================================= -->
            <div
                v-if="newlyCreated"
                class="bg-white rounded-3xl p-6 sm:p-7 border border-emerald-200 shadow-sm text-center space-y-5"
            >
                <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-3xl">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-xs font-mono font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
                            {{ newlyCreated.code }}
                        </span>
                        <span v-if="newlyCreated.zone" class="text-xs font-bold text-blue-800 bg-blue-50 px-2.5 py-1 rounded-lg flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-blue-600 text-[10px]"></i>
                            {{ newlyCreated.zone }}
                        </span>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 pt-2">
                        "{{ newlyCreated.nickname }}"
                    </h3>
                    <p v-if="newlyCreated.name" class="text-sm text-slate-600 font-medium">
                        {{ newlyCreated.name }}
                    </p>
                    <p class="text-xs text-slate-400 pt-1">
                        Cliente registrado correctamente y disponible para pedidos.
                    </p>
                </div>

                <!-- Botones de Acción Posterior -->
                <div class="space-y-2.5 pt-2">
                    <!-- Opción principal: Registrar servicio al paso -->
                    <Link
                        :href="route('repartidor.direct-service.create', { customer_id: newlyCreated.id })"
                        class="w-full py-3.5 px-4 rounded-2xl font-extrabold text-sm sm:text-base bg-emerald-600 text-white hover:bg-emerald-700 active:scale-98 transition shadow-sm inline-flex items-center justify-center gap-2"
                    >
                        <i class="fa-solid fa-bottle-water text-lg"></i> Registrar servicio a este cliente
                    </Link>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="registerAnother"
                            class="py-3 px-3 rounded-2xl font-bold text-xs sm:text-sm bg-slate-100 text-slate-700 hover:bg-slate-200 active:scale-98 transition inline-flex items-center justify-center gap-1.5"
                        >
                            <i class="fa-solid fa-user-plus"></i> Otro cliente
                        </button>

                        <Link
                            :href="route('repartidor.route.index')"
                            class="py-3 px-3 rounded-2xl font-bold text-xs sm:text-sm bg-blue-900 text-white hover:bg-blue-800 active:scale-98 transition inline-flex items-center justify-center gap-1.5"
                        >
                            <i class="fa-solid fa-route"></i> Ir a Mi Ruta
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 2. FORMULARIO DE ALTA RÁPIDA                                              -->
            <!-- ========================================================================= -->
            <form
                v-else
                @submit.prevent="submitCustomer"
                class="bg-white rounded-3xl p-5 sm:p-7 border border-slate-200/80 shadow-xs space-y-5"
            >
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-address-card text-blue-900"></i> Datos del Nuevo Cliente
                    </h3>
                    <p class="text-xs text-slate-500">
                        Solo ingresa el apodo con el que lo identificas. No es necesario solicitar teléfono, correo, contraseña ni domicilio.
                    </p>
                </div>

                <!-- Campo obligatorio: Apodo -->
                <div class="space-y-1.5">
                    <label for="nickname" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Apodo o Identificador del Cliente <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="nickname"
                        v-model="form.nickname"
                        type="text"
                        required
                        autofocus
                        placeholder="Ej. Don Beto, Doña Mary, Taller El Güero..."
                        class="w-full rounded-2xl border-slate-300 text-sm sm:text-base font-semibold focus:border-blue-900 focus:ring-blue-900 transition"
                    />
                    <InputError :message="form.errors.nickname" class="mt-1" />
                    <p class="text-[11px] text-slate-400">
                        Permite apodos repetidos sin mezclar clientes; cada uno recibe su propio código CLI único.
                    </p>
                </div>

                <!-- Campo opcional: Nombre completo -->
                <div class="space-y-1.5">
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Nombre completo <span class="text-slate-400 text-[11px] font-normal lowercase">(opcional)</span>
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ej. Alberto Pérez Dzul (si te lo proporciona)"
                        class="w-full rounded-2xl border-slate-300 text-sm focus:border-blue-900 focus:ring-blue-900 transition"
                    />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <!-- Selector de Zona de Reparto -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Zona de reparto <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button
                            v-for="zone in availableZones"
                            :key="zone.id"
                            type="button"
                            @click="form.delivery_zone = zone.id"
                            class="py-2.5 px-3 rounded-xl text-xs font-bold border transition flex items-center justify-center gap-1.5 min-h-[44px] cursor-pointer"
                            :class="form.delivery_zone === zone.id
                                ? 'bg-blue-900 text-white border-blue-900 shadow-2xs'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        >
                            <i class="fa-solid fa-location-dot text-xs" :class="form.delivery_zone === zone.id ? 'text-blue-300' : 'text-slate-400'"></i>
                            <span>{{ zone.label }}</span>
                        </button>
                    </div>
                    <InputError :message="form.errors.delivery_zone" class="mt-1" />
                    <p v-if="!form.delivery_zone" class="text-[11px] text-amber-600 mt-1 flex items-center gap-1 font-medium">
                        <i class="fa-solid fa-circle-info"></i> Selecciona la zona para ubicar al cliente en el listado de reparto.
                    </p>
                </div>

                <!-- Modalidad Exclusiva de Botellones (Fijos / Cambios / Sin botellones) -->
                <div class="space-y-2 p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Tipo de garrafones (20L)
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">Opcional</span>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            @click="selectType('exchange')"
                            class="py-2 px-2 rounded-xl text-xs font-bold border transition flex flex-col items-center justify-center min-h-[44px] cursor-pointer"
                            :class="bottleType === 'exchange'
                                ? 'bg-sky-50 text-sky-900 border-sky-400 ring-2 ring-sky-300'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        >
                            <span>Cambios</span>
                            <span class="text-[10px] font-normal text-slate-500">General</span>
                        </button>

                        <button
                            type="button"
                            @click="selectType('fixed')"
                            class="py-2 px-2 rounded-xl text-xs font-bold border transition flex flex-col items-center justify-center min-h-[44px] cursor-pointer"
                            :class="bottleType === 'fixed'
                                ? 'bg-blue-50 text-blue-900 border-blue-400 ring-2 ring-blue-300'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                        >
                            <span>Fijos</span>
                            <span class="text-[10px] font-normal text-slate-500">Rotulados</span>
                        </button>

                        <button
                            type="button"
                            @click="selectType('none')"
                            class="py-2 px-2 rounded-xl text-xs font-bold border transition flex flex-col items-center justify-center min-h-[44px] cursor-pointer"
                            :class="bottleType === 'none'
                                ? 'bg-slate-200 text-slate-800 border-slate-400'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                        >
                            <span>Ninguno</span>
                            <span class="text-[10px] font-normal text-slate-400">Sin asignar</span>
                        </button>
                    </div>

                    <!-- Cantidad si seleccionó Fijos o Cambios -->
                    <div v-if="bottleType !== 'none'" class="pt-2 flex items-center justify-between border-t border-slate-200/80">
                        <span class="text-xs font-semibold text-slate-700">
                            Cantidad de garrafones:
                        </span>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="if (bottlesCount > 1) { bottlesCount--; syncCounts(); }"
                                :disabled="bottlesCount <= 1"
                                class="w-9 h-9 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold hover:bg-slate-100 disabled:opacity-40"
                            >
                                -
                            </button>
                            <input
                                type="number"
                                min="1"
                                v-model.number="bottlesCount"
                                @input="syncCounts"
                                class="w-16 text-center font-mono font-bold text-sm rounded-xl border-slate-300"
                            />
                            <button
                                type="button"
                                @click="bottlesCount++; syncCounts();"
                                class="w-9 h-9 rounded-xl bg-white border border-slate-300 text-slate-700 font-bold hover:bg-slate-100"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Aviso informativo de agilidad -->
                <div class="p-3.5 rounded-2xl bg-blue-50/70 border border-blue-100 text-xs text-blue-900 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-bolt text-blue-700"></i> Guardado inmediato
                    </p>
                    <p class="text-slate-600 text-[11px] leading-relaxed">
                        Puedes registrar al cliente en cualquier momento aunque no tengas ruta iniciada ni pedido pendiente. Al terminar, el sistema te ofrecerá registrar un servicio si el cliente lo requiere.
                    </p>
                </div>

                <!-- Botones de Acción -->
                <div class="pt-2 flex flex-col sm:flex-row items-center gap-2.5">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.nickname.trim()"
                        class="w-full sm:flex-1 py-3.5 px-4 rounded-2xl font-extrabold text-sm sm:text-base bg-blue-900 text-white hover:bg-blue-800 disabled:opacity-50 active:scale-98 transition shadow-xs inline-flex items-center justify-center gap-2"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>{{ form.processing ? 'Guardando...' : 'Guardar cliente' }}</span>
                    </button>

                    <Link
                        :href="route('repartidor.route.index')"
                        class="w-full sm:w-auto py-3.5 px-5 rounded-2xl font-bold text-xs sm:text-sm bg-slate-100 text-slate-700 hover:bg-slate-200 transition text-center"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>

        </div>
    </AuthenticatedLayout>
</template>

