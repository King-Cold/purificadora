<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AddressForm from './Partials/AddressForm.vue';
import { MapPin } from 'lucide-vue-next';

const props = defineProps({
    address: {
        type: Object,
        required: true,
    },
    zones: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    municipality_type: props.address.is_hocaba === false ? 'other' : 'hocaba',
    other_municipality: props.address.is_hocaba === false ? (props.address.municipality || '') : '',
    delivery_zone: props.address.delivery_zone || '',
    street_and_number: props.address.street_and_number || '',
    reference: props.address.reference || '',
});

const submit = () => {
    form.put(route('cliente.addresses.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Editar domicilio" />

    <AuthenticatedLayout>
        <div class="bg-[#F5F4F8] py-4 sm:py-7">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
                <header class="rounded-3xl bg-gradient-to-br from-[#1E1545] via-[#2A1E5E] to-[#3B297D] text-white shadow-md p-5 sm:p-6 flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-2xl bg-white/15 border border-white/20 text-violet-200 flex items-center justify-center shrink-0">
                        <MapPin class="w-5 h-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                            Editar domicilio
                        </h1>
                        <p class="text-xs sm:text-sm text-violet-200 mt-0.5">
                            Actualiza tu calle, zona de reparto o referencias para el repartidor
                        </p>
                    </div>
                </header>

                <section class="rounded-2xl bg-white border border-[#E9E7F0] shadow-sm p-5 sm:p-8">
                    <AddressForm
                        :form="form"
                        :zones="zones"
                        submit-label="Actualizar domicilio"
                        submitting-label="Guardando..."
                        :cancel-href="route('cliente.addresses.index')"
                        @submit="submit"
                    />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
