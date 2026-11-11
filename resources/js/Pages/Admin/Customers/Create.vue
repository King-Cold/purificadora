<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const bottleType = ref('exchange'); // 'exchange', 'fixed', 'none'
const bottlesCount = ref(1);

const form = useForm({
    name: '',
    nickname: '',
    bottle_type: 'exchange',
    bottles_count: 1,
    fixed_bottles_count: 0,
    exchange_bottles_count: 1,
    notes: '',
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
    form.bottles_count = qty;
};

const incrementCount = () => {
    bottlesCount.value = (parseInt(bottlesCount.value, 10) || 0) + 1;
    syncCounts();
};

const decrementCount = () => {
    if (bottlesCount.value > 1) {
        bottlesCount.value = parseInt(bottlesCount.value, 10) - 1;
        syncCounts();
    }
};

const totalBottles = computed(() => {
    if (bottleType.value === 'none') return 0;
    return Math.max(0, parseInt(bottlesCount.value, 10) || 0);
});

const submit = () => {
    syncCounts();
    form.post(route('admin.customers.store'));
};
</script>

<template>
    <Head title="Nuevo Cliente Particular - Puritronic" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Clientes', href: route('admin.customers.index'), icon: 'fa-solid fa-address-book' },
                            { label: 'Nuevo Cliente', icon: 'fa-solid fa-user-plus' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold shadow-xs shrink-0">
                            <i class="fa-solid fa-user-plus text-base"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold leading-tight text-slate-900">
                                Registrar Cliente
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-500">
                                Registro administrativo para control de garrafones y reparto
                            </p>
                        </div>
                    </div>
                </div>

                <Link
                    :href="route('admin.customers.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition self-start sm:self-auto shadow-xs"
                >
                    <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                    <span>Volver al directorio</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Tarjeta Principal de Registro -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    
                    <!-- Encabezado simple -->
                    <div class="bg-slate-50 border-b border-slate-200 px-6 sm:px-8 py-4">
                        <h3 class="font-bold text-sm text-slate-900">
                            Alta de Cliente Sin Cuenta
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Se asignará automáticamente un código único (<span class="font-mono font-bold text-slate-700">CLI-XXXX</span>). Ingrese al menos el nombre o apodo para identificar al cliente.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-7">

                        <!-- SECCIÓN 1: IDENTIFICACIÓN -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200">
                                <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                    Identificación
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-5">
                                <!-- Nombre -->
                                <div class="sm:col-span-7">
                                    <div class="flex items-center justify-between">
                                        <InputLabel for="name" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                            Nombre
                                        </InputLabel>
                                        <span class="text-[11px] text-slate-400 font-medium">Opcional</span>
                                    </div>
                                    <TextInput
                                        id="name"
                                        type="text"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.name"
                                        autofocus
                                        placeholder="Ej. Juan Carlos Pérez Morales"
                                        autocomplete="name"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.name" />
                                </div>

                                <!-- Apodo -->
                                <div class="sm:col-span-5">
                                    <div class="flex items-center justify-between">
                                        <InputLabel for="nickname" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                            Apodo
                                        </InputLabel>
                                        <span class="text-[11px] text-slate-400 font-medium">Opcional</span>
                                    </div>
                                    <TextInput
                                        id="nickname"
                                        type="text"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.nickname"
                                        placeholder="Ej. Don Beto, Doña Mary..."
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.nickname" />
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: MODALIDAD Y BOTELLONES (REGLA DE EXCLUSIVIDAD) -->
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-2 border-b border-slate-200 gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-bottle-water"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                            Modalidad de Botellones (20 Litros)
                                        </h4>
                                        <p class="text-[11px] text-slate-500 font-normal">
                                            Cada cliente maneja exclusivamente botellones fijos o de cambio.
                                        </p>
                                    </div>
                                </div>

                                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-blue-900 text-white text-xs font-bold self-start sm:self-auto">
                                    <span>Total asignado:</span>
                                    <span class="text-sm font-mono font-bold">{{ totalBottles }}</span>
                                    <span>garrafones</span>
                                </div>
                            </div>

                            <!-- Selector de Modalidad Exclusiva (Fijos / Cambios / Sin botellones) -->
                            <div>
                                <InputLabel class="font-bold text-slate-900 text-xs sm:text-sm mb-2">
                                    Tipo de Botellón del Cliente
                                </InputLabel>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <!-- Botellones de Cambio -->
                                    <button
                                        type="button"
                                        @click="selectType('exchange')"
                                        :class="[
                                            'p-4 rounded-xl border text-left transition flex flex-col justify-between gap-2 cursor-pointer',
                                            bottleType === 'exchange'
                                                ? 'bg-sky-50 border-sky-400 ring-2 ring-sky-300/60 shadow-xs'
                                                : 'bg-white border-slate-200 hover:bg-slate-50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                                <i class="fa-solid fa-arrows-rotate text-sky-600"></i>
                                                Cambios
                                            </span>
                                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-sky-100 text-sky-800">
                                                Común
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500">
                                            Intercambia garrafones vacíos aptos por llenos en cada visita.
                                        </p>
                                    </button>

                                    <!-- Botellones Fijos -->
                                    <button
                                        type="button"
                                        @click="selectType('fixed')"
                                        :class="[
                                            'p-4 rounded-xl border text-left transition flex flex-col justify-between gap-2 cursor-pointer',
                                            bottleType === 'fixed'
                                                ? 'bg-blue-50 border-blue-400 ring-2 ring-blue-300/60 shadow-xs'
                                                : 'bg-white border-slate-200 hover:bg-slate-50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                                <i class="fa-solid fa-tag text-blue-900"></i>
                                                Fijos
                                            </span>
                                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-100 text-blue-900">
                                                Rotulados
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-500">
                                            El cliente conserva siempre sus propios envases rotulados.
                                        </p>
                                    </button>

                                    <!-- Sin Botellones -->
                                    <button
                                        type="button"
                                        @click="selectType('none')"
                                        :class="[
                                            'p-4 rounded-xl border text-left transition flex flex-col justify-between gap-2 cursor-pointer',
                                            bottleType === 'none'
                                                ? 'bg-slate-100 border-slate-400 ring-2 ring-slate-300 shadow-xs'
                                                : 'bg-white border-slate-200 hover:bg-slate-50'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-slate-700 flex items-center gap-2">
                                                <i class="fa-solid fa-circle-minus text-slate-400"></i>
                                                Sin botellones
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            Alta rápida sin asignar envases por el momento.
                                        </p>
                                    </button>
                                </div>
                                <InputError class="mt-1.5" :message="form.errors.bottle_type" />
                            </div>

                            <!-- Campo Único: Cantidad de Botellones -->
                            <div v-if="bottleType !== 'none'" class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div>
                                        <InputLabel for="bottles_count" class="font-bold text-slate-900 text-xs sm:text-sm">
                                            Cantidad de botellones (20 Litros)
                                        </InputLabel>
                                        <p class="text-[11px] text-slate-500">
                                            Indica cuántos botellones {{ bottleType === 'fixed' ? 'fijos rotulados' : 'de cambio' }} tiene el cliente.
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2 self-start sm:self-auto">
                                        <button
                                            type="button"
                                            @click="decrementCount"
                                            :disabled="bottlesCount <= 1"
                                            class="w-11 h-11 rounded-xl bg-white border border-slate-300 text-slate-700 flex items-center justify-center font-bold text-lg hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition shadow-2xs"
                                            title="Disminuir"
                                            aria-label="Disminuir cantidad"
                                        >
                                            <i class="fa-solid fa-minus text-xs"></i>
                                        </button>

                                        <TextInput
                                            id="bottles_count"
                                            type="number"
                                            min="1"
                                            step="1"
                                            class="w-20 text-center font-mono font-bold text-base rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                            v-model.number="bottlesCount"
                                            @input="syncCounts"
                                            required
                                            aria-label="Cantidad de botellones"
                                        />

                                        <button
                                            type="button"
                                            @click="incrementCount"
                                            class="w-11 h-11 rounded-xl bg-white border border-slate-300 text-slate-700 flex items-center justify-center font-bold text-lg hover:bg-slate-100 cursor-pointer transition shadow-2xs"
                                            title="Aumentar"
                                            aria-label="Aumentar cantidad"
                                        >
                                            <i class="fa-solid fa-plus text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                                <InputError class="mt-1.5" :message="form.errors.bottles_count || form.errors.fixed_bottles_count || form.errors.exchange_bottles_count" />
                            </div>
                        </div>

                        <!-- SECCIÓN 3: OBSERVACIONES O NOTAS DE REPARTO -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                        <i class="fa-solid fa-note-sticky"></i>
                                    </div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                        Observaciones o Notas de Reparto
                                    </h4>
                                </div>
                                <span class="text-[11px] font-medium text-slate-500">
                                    Opcional
                                </span>
                            </div>

                            <div>
                                <textarea
                                    id="notes"
                                    rows="3"
                                    v-model="form.notes"
                                    class="block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900 placeholder-slate-400"
                                    placeholder="Ej. Casa de rejas blancas, tocar portón; acostumbra pedir los lunes..."
                                ></textarea>
                                <InputError class="mt-1.5" :message="form.errors.notes" />
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN -->
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-200">
                            <Link
                                :href="route('admin.customers.index')"
                                class="w-full sm:w-auto text-center px-5 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition shadow-xs"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-blue-900 hover:bg-blue-800 active:bg-blue-950 border border-transparent rounded-xl font-bold text-sm text-white shadow-xs focus:outline-none focus:ring-2 focus:ring-blue-900 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i v-if="!form.processing" class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>{{ form.processing ? 'Guardando cliente...' : 'Guardar cliente' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
