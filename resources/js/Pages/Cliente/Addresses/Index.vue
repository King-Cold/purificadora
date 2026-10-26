<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    address: {
        type: Object,
        default: null,
    },
    customer: {
        type: Object,
        default: () => ({}),
    },
    zones: {
        type: Object,
        default: () => ({}),
    },
});

const confirmingAddressDeletion = ref(false);
const isDeleting = ref(false);

// 1. Calle y número (destacado como información principal)
const streetAndNumber = computed(() => {
    if (!props.address) return '';
    const street = props.address.street?.trim() || '';
    const ext = props.address.exterior_number?.trim();
    const interior = props.address.interior_number?.trim();

    let result = street;
    if (ext && ext !== 'S/N' && !street.toLowerCase().includes(ext.toLowerCase())) {
        result += ` #${ext}`;
    } else if (ext === 'S/N' && !street.toLowerCase().includes('s/n') && !street.toLowerCase().includes('sin número')) {
        result += ' S/N';
    }

    if (interior && !street.toLowerCase().includes(interior.toLowerCase())) {
        result += `, Int. ${interior}`;
    }

    return result || 'Dirección sin especificar';
});

// 2. Cruzamientos (si existe)
const betweenStreets = computed(() => {
    if (!props.address?.between_streets?.trim()) return null;
    return props.address.between_streets.trim();
});

// 3. Colonia, localidad y estado
const locationLine = computed(() => {
    if (!props.address) return '';
    const parts = [];

    if (props.address.neighborhood?.trim()) {
        const neigh = props.address.neighborhood.trim();
        parts.push(
            neigh.toLowerCase().startsWith('col.') || neigh.toLowerCase().startsWith('colonia')
                ? neigh
                : `Col. ${neigh}`
        );
    }

    const city = props.address.city?.trim() || props.address.municipality?.trim();
    if (city) {
        parts.push(city);
    }

    const state = props.address.state?.trim() || 'Yucatán';
    if (state && (!city || !city.toLowerCase().includes(state.toLowerCase()))) {
        parts.push(state);
    }

    return parts.join(', ');
});

// 4. Código postal (si existe)
const postalCode = computed(() => {
    if (!props.address?.postal_code?.trim()) return null;
    return props.address.postal_code.trim();
});

// 5. Aviso si faltan datos indispensables (calle o zona en Hocabá)
const missingDataNotice = computed(() => {
    if (!props.address) return null;
    const missing = [];

    if (!props.address.street?.trim()) {
        missing.push('calle');
    }
    if (props.address.is_hocaba && !props.address.delivery_zone) {
        missing.push('zona de reparto');
    }

    if (missing.length > 0) {
        return `Falta registrar: ${missing.join(' y ')}. Edita tu domicilio para asegurar la correcta entrega de tus pedidos.`;
    }
    return null;
});

const deleteAddress = () => {
    isDeleting.value = true;
    router.delete(route('cliente.addresses.destroy'), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            confirmingAddressDeletion.value = false;
        },
    });
};
</script>

<template>
    <Head title="Mi domicilio - Puritronic" />

    <AuthenticatedLayout>
        <!-- Encabezado: "Mi domicilio" y "Aquí entregaremos tus pedidos" con navegación de regreso sencilla -->
        <template #header>
            <div class="flex items-center gap-3.5">
                <Link
                    :href="route('cliente.dashboard')"
                    class="h-10 w-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-900/20"
                    title="Volver"
                    aria-label="Volver"
                >
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </Link>
                <div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
                        Mi domicilio
                    </h1>
                    <p class="text-xs text-slate-500">
                        Aquí entregaremos tus pedidos
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8 bg-slate-50/60 min-h-[calc(100vh-4rem)]">
            <!-- Columna central con ancho máximo aproximado de 800 px -->
            <div class="mx-auto max-w-[800px] px-4 sm:px-6 space-y-5">

                <!-- Mensaje de Éxito Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-3.5 sm:p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center gap-2.5 text-xs sm:text-sm"
                >
                    <i class="fa-solid fa-circle-check text-emerald-600 shrink-0"></i>
                    <span>{{ $page.props.flash.success }}</span>
                </div>

                <!-- Mensaje de Advertencia Flash -->
                <div
                    v-if="$page.props.flash?.warning"
                    class="p-3.5 sm:p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 flex items-center gap-2.5 text-xs sm:text-sm"
                >
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 shrink-0"></i>
                    <span>{{ $page.props.flash.warning }}</span>
                </div>

                <!-- Tarjeta única del Domicilio -->
                <div
                    v-if="address"
                    class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6"
                >
                    <!-- Encabezado de la tarjeta con zona de reparto como etiqueta secundaria -->
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
                                Dirección de entrega
                            </span>
                            <!-- Calle y número destacado como información principal -->
                            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug break-words">
                                {{ streetAndNumber }}
                            </h2>
                        </div>

                        <!-- Zona de reparto mostrada una sola vez como etiqueta secundaria -->
                        <div v-if="address.zone_label" class="self-start">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80">
                                {{ address.zone_label }}
                            </span>
                        </div>
                    </div>

                    <!-- Líneas organizadas: Cruzamientos, Colonia/Localidad/Estado, Código postal -->
                    <div class="space-y-1.5 text-sm text-slate-600">
                        <!-- Cruzamientos -->
                        <p v-if="betweenStreets" class="break-words">
                            <span class="text-slate-400 font-medium">Cruzamientos:</span> {{ betweenStreets }}
                        </p>

                        <!-- Colonia, localidad y estado -->
                        <p v-if="locationLine" class="break-words">
                            {{ locationLine }}
                        </p>

                        <!-- Código postal -->
                        <p v-if="postalCode" class="text-slate-500">
                            <span class="text-slate-400 font-medium">C.P.</span> {{ postalCode }}
                        </p>
                    </div>

                    <!-- Referencias debajo, con texto normal, sin comillas ni cursivas -->
                    <div v-if="address.reference?.trim()" class="pt-5 border-t border-slate-100 space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 block">
                            Referencias
                        </span>
                        <p class="text-sm text-slate-700 leading-relaxed break-words">
                            {{ address.reference.trim() }}
                        </p>
                    </div>

                    <!-- Aviso breve si faltan datos indispensables -->
                    <div
                        v-if="missingDataNotice"
                        class="p-3.5 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-900 text-xs sm:text-sm flex items-start gap-2.5"
                    >
                        <i class="fa-solid fa-circle-exclamation text-amber-600 mt-0.5 shrink-0"></i>
                        <span>{{ missingDataNotice }}</span>
                    </div>

                    <!-- Acciones: "Editar domicilio" como principal en azul y "Eliminar domicilio" como secundaria discreta al final -->
                    <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <Link
                            :href="route('cliente.addresses.edit')"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 transition shadow-xs cursor-pointer min-h-[44px] focus:outline-none focus:ring-2 focus:ring-blue-900/30"
                        >
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                            <span>Editar domicilio</span>
                        </Link>

                        <button
                            type="button"
                            @click="confirmingAddressDeletion = true"
                            class="text-xs text-slate-400 hover:text-rose-600 font-medium transition cursor-pointer py-2 px-3 rounded focus:outline-none focus:ring-2 focus:ring-rose-500/20"
                        >
                            Eliminar domicilio
                        </button>
                    </div>
                </div>

                <!-- Estado Vacío Sin Domicilio -->
                <div
                    v-else
                    class="bg-white rounded-2xl p-8 sm:p-12 text-center shadow-xs border border-slate-200/80 space-y-4"
                >
                    <div class="h-12 w-12 mx-auto rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
                        <i class="fa-solid fa-location-dot text-xl"></i>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-base sm:text-lg font-bold text-slate-900">
                            Aún no has registrado tu domicilio
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                            Para poder llevarte los garrafones de agua Puritronic, necesitamos registrar la dirección de tu casa en Hocabá.
                        </p>
                    </div>
                    <div class="pt-2">
                        <Link
                            :href="route('cliente.addresses.create')"
                            class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-blue-900 hover:bg-blue-800 rounded-xl transition shadow-xs cursor-pointer min-h-[44px] focus:outline-none focus:ring-2 focus:ring-blue-900/30"
                        >
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Registrar mi domicilio</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal de Confirmación para Eliminar Domicilio -->
        <Modal :show="confirmingAddressDeletion" @close="confirmingAddressDeletion = false" max-width="md">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3 text-slate-900">
                    <div class="h-10 w-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            ¿Eliminar domicilio de entrega?
                        </h3>
                        <p class="text-xs text-slate-500">
                            Esta acción no se puede deshacer.
                        </p>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    Necesitarás registrar un nuevo domicilio para poder solicitar pedidos de garrafones a domicilio.
                </p>

                <div class="pt-3 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                    <SecondaryButton @click="confirmingAddressDeletion = false" class="justify-center min-h-[44px]">
                        Cancelar
                    </SecondaryButton>
                    <DangerButton @click="deleteAddress" :disabled="isDeleting" class="justify-center min-h-[44px]">
                        <span v-if="isDeleting">Eliminando...</span>
                        <span v-else>Eliminar domicilio</span>
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
