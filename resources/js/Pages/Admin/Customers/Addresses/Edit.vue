<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    address: {
        type: Object,
        required: true,
    },
    zones: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    municipality_type: props.address.municipality_type || 'hocaba',
    other_municipality: props.address.other_municipality || '',
    delivery_zone: props.address.delivery_zone || '',
    street_and_number: props.address.street_and_number || '',
    reference: props.address.reference || '',
});

const submit = () => {
    form.put(route('admin.customers.addresses.update', [props.customer.id, props.address.id]));
};
</script>

<template>
    <Head :title="`Editar Domicilio - ${customer.name || customer.nickname || customer.code} - Puritronic`" />

    <AuthenticatedLayout :wide="true">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <Breadcrumbs
                        :items="[
                            { label: 'Clientes', href: route('admin.customers.index'), icon: 'fa-solid fa-address-book' },
                            { label: customer.code, href: route('admin.customers.show', customer.id), icon: 'fa-solid fa-id-badge' },
                            { label: 'Editar Domicilio', icon: 'fa-solid fa-location-pen' }
                        ]"
                    />
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold leading-tight text-gray-900">
                            Editar Domicilio (Casa)
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-md bg-blue-100 text-blue-900 font-mono font-semibold text-xs">
                            {{ customer.code }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Cliente: <strong>{{ customer.name || (customer.nickname ? `"${customer.nickname}"` : 'Particular') }}</strong>
                    </p>
                </div>

                <Link
                    :href="route('admin.customers.show', customer.id)"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition self-start sm:self-auto shadow-sm"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Volver a la ficha</span>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto w-full max-w-4xl 2xl:max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- 1. Municipio -->
                        <div class="space-y-3">
                            <InputLabel value="Municipio de ubicación *" />

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                    :class="form.municipality_type === 'hocaba' ? 'bg-blue-50/50 border-blue-600 ring-2 ring-blue-600/20' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input
                                        type="radio"
                                        value="hocaba"
                                        v-model="form.municipality_type"
                                        class="h-4 w-4 text-blue-900 border-gray-300 focus:ring-blue-900"
                                    />
                                    <div>
                                        <span class="text-sm font-bold text-gray-900 block">Hocabá, Yucatán</span>
                                        <span class="text-xs text-gray-500">Municipio predeterminado</span>
                                    </div>
                                </label>

                                <label
                                    class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                                    :class="form.municipality_type === 'other' ? 'bg-blue-50/50 border-blue-600 ring-2 ring-blue-600/20' : 'border-gray-200 hover:bg-gray-50'"
                                >
                                    <input
                                        type="radio"
                                        value="other"
                                        v-model="form.municipality_type"
                                        class="h-4 w-4 text-blue-900 border-gray-300 focus:ring-blue-900"
                                    />
                                    <div>
                                        <span class="text-sm font-bold text-gray-900 block">Otro municipio</span>
                                        <span class="text-xs text-gray-500">Ubicación foránea</span>
                                    </div>
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors.municipality_type" />

                            <div v-if="form.municipality_type === 'other'" class="pt-2">
                                <InputLabel for="other_municipality" value="Nombre del municipio *" />
                                <TextInput
                                    id="other_municipality"
                                    type="text"
                                    class="mt-1 block w-full text-sm"
                                    v-model="form.other_municipality"
                                    placeholder="Ej. Seyé, Tahmek, Sanahcat, Mérida"
                                    required
                                />
                                <InputError class="mt-1.5" :message="form.errors.other_municipality" />
                            </div>
                        </div>

                        <!-- 2. Zona de Reparto (Solo para Hocabá) -->
                        <div v-if="form.municipality_type === 'hocaba'" class="space-y-2 pt-2 border-t border-gray-100">
                            <InputLabel value="Zona de reparto en Hocabá *" />
                            <p class="text-xs text-gray-500 mb-2">
                                Seleccione la zona correspondiente para la ruta del repartidor.
                            </p>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <button
                                    type="button"
                                    v-for="(label, key) in zones"
                                    :key="key"
                                    @click="form.delivery_zone = key"
                                    class="p-3 text-center rounded-xl border text-sm font-semibold transition"
                                    :class="form.delivery_zone === key ? 'bg-blue-900 text-white border-blue-900 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50'"
                                >
                                    {{ label }}
                                </button>
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.delivery_zone" />
                        </div>

                        <!-- 3. Calle y número -->
                        <div class="pt-2 border-t border-gray-100">
                            <InputLabel for="street_and_number" value="Calle y número *" />
                            <TextInput
                                id="street_and_number"
                                type="text"
                                class="mt-1 block w-full text-sm"
                                v-model="form.street_and_number"
                                placeholder="Ej. Calle 20 #105 o Calle 22 entre 15 y 17 S/N"
                                required
                            />
                            <InputError class="mt-1.5" :message="form.errors.street_and_number" />
                            <span class="text-xs text-gray-400 mt-1 block">
                                Ingrese el nombre o número de la calle y el número de la casa (o indicar S/N).
                            </span>
                        </div>

                        <!-- 4. Referencias para el repartidor (opcional) -->
                        <div class="pt-2 border-t border-gray-100">
                            <InputLabel for="reference" value="Referencias para el repartidor (opcional)" />
                            <textarea
                                id="reference"
                                rows="3"
                                v-model="form.reference"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-900 focus:ring-blue-900 text-sm"
                                placeholder="Ej. Casa verde frente al parque central, reja blanca de herrería"
                            ></textarea>
                            <InputError class="mt-1.5" :message="form.errors.reference" />
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link
                                :href="route('admin.customers.show', customer.id)"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-blue-900 rounded-lg hover:bg-blue-800 transition shadow-sm disabled:opacity-50"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar cambios' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
