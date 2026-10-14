<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    zones: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    municipality_type: 'hocaba',
    other_municipality: '',
    delivery_zone: '',
    street_and_number: '',
    reference: '',
});

const submit = () => {
    form.post(route('cliente.addresses.store'));
};
</script>

<template>
    <Head title="Registrar Mi Domicilio - Puritronic" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link :href="route('cliente.addresses.index')" class="hover:text-blue-900 transition">
                            Mi Domicilio
                        </Link>
                        <span>/</span>
                        <span class="text-gray-900 font-medium">Registrar</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900">
                        Registrar Mi Domicilio
                    </h2>
                    <p class="text-sm text-gray-500">
                        Indica los datos de tu casa para la entrega a domicilio de tus garrafones
                    </p>
                </div>

                <Link
                    :href="route('cliente.addresses.index')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition self-start sm:self-auto shadow-xs"
                >
                    <i class="fa-solid fa-arrow-left text-slate-400"></i>
                    <span>Volver</span>
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8 lg:py-10 bg-slate-50/60 min-h-[calc(100vh-8rem)]">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                    <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">

                        <!-- Encabezado informativo -->
                        <div class="p-4 rounded-xl bg-sky-50/70 border border-sky-100 text-sky-950 text-xs flex items-start gap-3">
                            <i class="fa-solid fa-house-chimney text-sky-600 text-base mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <strong class="font-bold block text-sm mb-0.5 text-slate-900">Domicilio de Entrega</strong>
                                Tu cuenta cuenta con un único domicilio registrado correspondiente a tu casa.
                            </div>
                        </div>

                        <!-- 1. Municipio -->
                        <div class="space-y-3">
                            <InputLabel value="Municipio de tu casa *" />

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
                                        <span class="text-xs text-gray-500">Predeterminado</span>
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
                                        <span class="text-xs text-gray-500">Ubicación fuera de Hocabá</span>
                                    </div>
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors.municipality_type" />

                            <!-- Campo si seleccionó Otro Municipio -->
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
                                Selecciona la zona de Hocabá donde se ubica tu casa.
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
                                Ingrese el número o nombre de calle y el número exterior (o S/N).
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
                            <span class="text-xs text-gray-400 mt-1 block">
                                Color de fachada, portón o punto de referencia conocido.
                            </span>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <Link
                                :href="route('cliente.addresses.index')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm"
                            >
                                Cancelar
                            </Link>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-blue-900 rounded-xl hover:bg-blue-800 transition shadow-xs disabled:opacity-50"
                            >
                                <i v-if="form.processing" class="fa-solid fa-spinner fa-spin mr-1"></i>
                                <i v-else class="fa-solid fa-floppy-disk mr-1"></i>
                                <span>{{ form.processing ? 'Guardando...' : 'Guardar mi domicilio' }}</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
