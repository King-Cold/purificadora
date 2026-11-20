<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    MapPin,
    Navigation,
    PenSquare,
    Trash2,
    Plus,
    AlertTriangle,
    CheckCircle2,
    Home,
    Info,
} from 'lucide-vue-next';

const props = defineProps({
    address: {
        type: Object,
        default: null,
    },
    customer: {
        type: Object,
        default: null,
    },
    zones: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showDeleteModal = ref(false);
const isDeleting = ref(false);

const streetAndNumber = computed(() => {
    if (!props.address) return '';
    return [props.address.street_and_number || props.address.street, props.address.exterior_number ? `#${props.address.exterior_number}` : null]
        .filter(Boolean)
        .join(' ');
});

const betweenStreets = computed(() => props.address?.between_streets || '');

const locationLine = computed(() => {
    if (!props.address) return '';
    return [props.address.neighborhood, props.address.municipality, props.address.state]
        .filter(Boolean)
        .join(', ');
});

const postalCode = computed(() => props.address?.postal_code || '');

const missingDataNotice = computed(() => {
    if (!props.address) return null;
    if (!props.address.delivery_zone) {
        return 'Tu domicilio aún no tiene una zona de reparto asignada. Edítalo para acelerar tus entregas.';
    }
    return null;
});

const confirmDelete = () => {
    isDeleting.value = true;
    router.delete(route('cliente.addresses.destroy'), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Mi domicilio de entrega" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
                <!-- ========================================== -->
                <!-- 1. ENCABEZADO PURITRONIC                   -->
                <!-- ========================================== -->
                <header class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <MapPin class="w-6 h-6" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-xl sm:text-2xl font-extrabold text-[#1E1545] tracking-tight">
                                Mi domicilio de entrega
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-600 mt-0.5">
                                Dirección predeterminada donde recibirás tus botellones Puritronic
                            </p>
                        </div>
                    </div>

                    <Link
                        v-if="address"
                        :href="route('cliente.addresses.edit')"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                    >
                        <PenSquare class="w-4 h-4" aria-hidden="true" />
                        <span>Editar domicilio</span>
                    </Link>

                    <Link
                        v-else
                        :href="route('cliente.addresses.create')"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                    >
                        <Plus class="w-4 h-4" aria-hidden="true" />
                        <span>Registrar domicilio</span>
                    </Link>
                </header>

                <!-- Alertas Flash -->
                <div
                    v-if="flashSuccess"
                    role="status"
                    class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3 text-sm font-semibold text-emerald-950"
                >
                    <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" aria-hidden="true" />
                    <span>{{ flashSuccess }}</span>
                </div>

                <div
                    v-if="flashError"
                    role="alert"
                    class="rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-center gap-3 text-sm font-semibold text-rose-950"
                >
                    <AlertTriangle class="w-5 h-5 text-rose-600 shrink-0" aria-hidden="true" />
                    <span>{{ flashError }}</span>
                </div>

                <!-- Aviso de datos incompletos -->
                <div
                    v-if="missingDataNotice"
                    class="rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-xs sm:text-sm font-medium text-amber-950"
                >
                    <Info class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
                    <span>{{ missingDataNotice }}</span>
                </div>

                <!-- ========================================== -->
                <!-- 2. TARJETA DEL DOMICILIO REGISTRADO        -->
                <!-- ========================================== -->
                <section
                    v-if="address"
                    aria-label="Detalles de tu domicilio"
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm overflow-hidden"
                >
                    <div class="p-6 sm:p-8 space-y-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex items-start gap-3.5">
                                <div class="h-11 w-11 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200/70 flex items-center justify-center shrink-0">
                                    <Home class="w-5 h-5" aria-hidden="true" />
                                </div>
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">
                                        Calle y número
                                    </span>
                                    <h2 class="text-lg sm:text-xl font-extrabold text-[#1E1545] break-words mt-0.5">
                                        {{ streetAndNumber }}
                                    </h2>
                                    <p v-if="betweenStreets" class="text-sm text-slate-600 mt-1 break-words">
                                        {{ betweenStreets }}
                                    </p>
                                </div>
                            </div>

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800">
                                <Navigation class="w-3.5 h-3.5 text-emerald-600" aria-hidden="true" />
                                <span>{{ address.zone_label }}</span>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-[#E9E7F0]">
                            <div class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-4">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                    Localidad / Municipio
                                </span>
                                <p class="text-sm font-bold text-[#1E1545] mt-1">
                                    {{ locationLine || 'Hocabá, Yucatán' }}
                                </p>
                                <p v-if="postalCode" class="text-xs text-slate-500 mt-0.5 tabular-nums">
                                    C.P. {{ postalCode }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-[#F5F4F8] border border-[#E9E7F0] p-4">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">
                                    Referencias para entrega
                                </span>
                                <p class="text-sm text-slate-700 mt-1 break-words">
                                    {{ address.reference || 'Sin referencias adicionales registradas.' }}
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-[#E9E7F0] flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500">
                                Los pedidos ya registrados conservan la dirección con la que fueron creados.
                            </p>

                            <button
                                type="button"
                                @click="showDeleteModal = true"
                                class="inline-flex items-center gap-1.5 px-4 py-2 min-h-[44px] rounded-xl text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600"
                            >
                                <Trash2 class="w-4 h-4" aria-hidden="true" />
                                <span>Eliminar domicilio</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Estado vacío -->
                <section
                    v-else
                    class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-8 sm:p-10 text-center space-y-4"
                >
                    <div class="mx-auto h-14 w-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <MapPin class="w-7 h-7" aria-hidden="true" />
                    </div>
                    <div class="space-y-1 max-w-md mx-auto">
                        <h2 class="text-base sm:text-lg font-extrabold text-[#1E1545]">
                            Aún no has registrado tu domicilio
                        </h2>
                        <p class="text-xs sm:text-sm text-slate-600">
                            Agrega tu dirección y zona de entrega para poder solicitar botellones a domicilio con un solo clic.
                        </p>
                    </div>
                    <div class="pt-1">
                        <Link
                            :href="route('cliente.addresses.create')"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 min-h-[44px] rounded-xl text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition"
                        >
                            <Plus class="w-4 h-4" aria-hidden="true" />
                            <span>Registrar mi domicilio</span>
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar -->
        <div
            v-if="showDeleteModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-address-title"
            class="fixed inset-0 z-50 overflow-y-auto bg-[#1E1545]/50 backdrop-blur-sm flex items-center justify-center p-4"
            @keydown.escape="showDeleteModal = false"
        >
            <div class="w-full max-w-md max-h-[90dvh] overflow-y-auto rounded-2xl bg-white border border-[#E9E7F0] shadow-xl p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <AlertTriangle class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 id="delete-address-title" class="text-base font-extrabold text-[#1E1545]">
                            ¿Eliminar tu domicilio registrado?
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 mt-1">
                            Necesitarás registrar un nuevo domicilio antes de volver a pedir agua a domicilio.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showDeleteModal = false"
                        class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-slate-700 bg-[#F5F4F8] hover:bg-[#ECEAEF] transition cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="px-4 py-2.5 min-h-[44px] rounded-xl text-xs sm:text-sm font-bold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 transition cursor-pointer"
                    >
                        {{ isDeleting ? 'Eliminando...' : 'Sí, eliminar' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
