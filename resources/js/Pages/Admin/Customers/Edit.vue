<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const initialType = computed(() => {
    if (Number(props.customer.fixed_bottles_count) > 0) return 'fixed';
    if (Number(props.customer.exchange_bottles_count) > 0) return 'exchange';
    return 'none';
});

const bottleType = ref(initialType.value);

const initialCount = computed(() => {
    if (bottleType.value === 'fixed') return Number(props.customer.fixed_bottles_count) || 1;
    if (bottleType.value === 'exchange') return Number(props.customer.exchange_bottles_count) || 1;
    return 0;
});

const bottlesCount = ref(initialCount.value);

const form = useForm({
    name: props.customer.name || '',
    nickname: props.customer.nickname || '',
    phone: props.customer.phone || '',
    secondary_phone: props.customer.secondary_phone || '',
    email: props.customer.email || '',
    bottle_type: bottleType.value,
    bottles_count: bottlesCount.value,
    fixed_bottles_count: props.customer.fixed_bottles_count ?? 0,
    exchange_bottles_count: props.customer.exchange_bottles_count ?? 0,
    notes: props.customer.notes || '',
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
    form.put(route('admin.customers.update', props.customer.id));
};
</script>

<template>
    <Head :title="`Editar Cliente: ${customer.name || customer.nickname || customer.code} - Puritronic`" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Clientes', href: route('admin.customers.index'), icon: 'fa-solid fa-address-book' },
                            { label: customer.code, href: route('admin.customers.show', customer.id), icon: 'fa-solid fa-id-badge' },
                            { label: 'Editar', icon: 'fa-solid fa-user-pen' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-bold text-sm shadow-xs shrink-0">
                            <i class="fa-solid fa-user-pen text-base"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-xl font-bold leading-tight text-slate-900">
                                    Editar Cliente
                                </h2>
                                <span class="px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-800 font-mono font-bold text-xs border border-slate-200">
                                    {{ customer.code }}
                                </span>
                                <span
                                    v-if="customer.has_account"
                                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200"
                                >
                                    Con cuenta
                                </span>
                                <span
                                    v-else
                                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-300"
                                >
                                    Sin cuenta
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                                Actualice los datos del cliente y la cantidad de botellones registrados
                            </p>
                        </div>
                    </div>
                </div>

                <Link
                    :href="route('admin.customers.show', customer.id)"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition self-start sm:self-auto shadow-xs"
                >
                    <i class="fa-solid fa-arrow-left text-xs text-slate-500"></i>
                    <span>Ver ficha del cliente</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Error general de cambio de modalidad si existen envases en custodia o pedidos activos -->
                <div
                    v-if="form.errors.bottle_type"
                    class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-950 flex items-start gap-3 text-xs sm:text-sm shadow-xs"
                >
                    <i class="fa-solid fa-circle-xmark text-rose-600 text-lg mt-0.5 shrink-0"></i>
                    <div class="font-medium leading-relaxed">{{ form.errors.bottle_type }}</div>
                </div>

                <!-- Tarjeta Principal de Edición -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
                    
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

                        <!-- SECCIÓN 2: CONTACTO (Solo si el cliente tiene cuenta registrada) -->
                        <div v-if="customer.has_account" class="space-y-4">
                            <div class="flex items-center gap-2.5 pb-2 border-b border-slate-200">
                                <div class="w-7 h-7 rounded-lg bg-blue-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 uppercase tracking-wider">
                                    Medios de Contacto
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <InputLabel for="phone" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        Teléfono
                                    </InputLabel>
                                    <TextInput
                                        id="phone"
                                        type="tel"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.phone"
                                        placeholder="Ej. 9991234567"
                                        autocomplete="tel"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.phone" />
                                </div>

                                <div>
                                    <InputLabel for="email" class="font-semibold text-slate-700 text-xs sm:text-sm">
                                        Correo electrónico
                                    </InputLabel>
                                    <TextInput
                                        id="email"
                                        type="email"
                                        class="mt-1.5 block w-full text-sm rounded-xl border-slate-300 focus:border-blue-900 focus:ring-blue-900"
                                        v-model="form.email"
                                        placeholder="cliente@ejemplo.com"
                                        autocomplete="email"
                                    />
                                    <InputError class="mt-1.5" :message="form.errors.email" />
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 3: MODALIDAD Y BOTELLONES (REGLA DE EXCLUSIVIDAD) -->
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
                                            Intercambia garrafones vacíos por llenos en cada entrega.
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
                                            El cliente conserva sus propios garrafones rotulados.
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
                                            Cliente sin garrafones registrados asignados.
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

                        <!-- SECCIÓN 4: OBSERVACIONES O NOTAS DE REPARTO -->
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
                                    placeholder="Ej. Casa de rejas blancas, tocar portón..."
                                ></textarea>
                                <InputError class="mt-1.5" :message="form.errors.notes" />
                            </div>
                        </div>

                        <!-- BOTONES DE ACCIÓN -->
                        <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-200">
                            <Link
                                :href="route('admin.customers.show', customer.id)"
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
                                <span>{{ form.processing ? 'Guardando cambios...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
