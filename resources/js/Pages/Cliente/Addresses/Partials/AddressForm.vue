<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import {
    MapPin,
    Navigation,
    CheckCircle2,
    Save,
    AlertTriangle,
} from 'lucide-vue-next';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    zones: {
        type: Object,
        default: () => ({}),
    },
    submitLabel: {
        type: String,
        default: 'Guardar domicilio',
    },
    submittingLabel: {
        type: String,
        default: 'Guardando...',
    },
    cancelHref: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['submit']);

const zoneEntries = computed(() => Object.entries(props.zones || {}));

const selectHocaba = () => {
    props.form.municipality_type = 'hocaba';
    props.form.other_municipality = '';
};

const selectOtherMunicipality = () => {
    props.form.municipality_type = 'other';
};
</script>

<template>
    <form @submit.prevent="emit('submit')" class="space-y-6">
        <!-- Errores generales si los hay -->
        <div
            v-if="Object.keys(form.errors).length > 0"
            role="alert"
            class="rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-start gap-3 text-xs sm:text-sm text-rose-900"
        >
            <AlertTriangle class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" aria-hidden="true" />
            <div>
                <p class="font-bold">Por favor revisa los campos marcados antes de guardar.</p>
            </div>
        </div>

        <!-- 1. Municipio -->
        <div class="space-y-2.5">
            <InputLabel value="Municipio o localidad *" class="text-[#1E1545] font-bold" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Selecciona tu municipio">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="form.municipality_type === 'hocaba'"
                    @click="selectHocaba"
                    class="p-4 min-h-[56px] rounded-2xl border text-left transition flex items-center justify-between gap-3 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0275B8]"
                    :class="form.municipality_type === 'hocaba'
                        ? 'bg-sky-50 border-[#0275B8] ring-1 ring-[#0275B8]/40 text-[#1E1545]'
                        : 'bg-white border-[#E9E7F0] text-slate-600 hover:bg-[#F5F4F8]'"
                >
                    <div>
                        <span class="text-sm font-extrabold block">Hocabá</span>
                        <span class="text-xs text-slate-500">Cabecera municipal</span>
                    </div>
                    <CheckCircle2
                        class="w-5 h-5 shrink-0"
                        :class="form.municipality_type === 'hocaba' ? 'text-[#0275B8]' : 'text-slate-300'"
                        aria-hidden="true"
                    />
                </button>

                <button
                    type="button"
                    role="radio"
                    :aria-checked="form.municipality_type === 'other'"
                    @click="selectOtherMunicipality"
                    class="p-4 min-h-[56px] rounded-2xl border text-left transition flex items-center justify-between gap-3 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0275B8]"
                    :class="form.municipality_type === 'other'
                        ? 'bg-sky-50 border-[#0275B8] ring-1 ring-[#0275B8]/40 text-[#1E1545]'
                        : 'bg-white border-[#E9E7F0] text-slate-600 hover:bg-[#F5F4F8]'"
                >
                    <div>
                        <span class="text-sm font-extrabold block">Otra comunidad</span>
                        <span class="text-xs text-slate-500">Comisaría o localidad cercana</span>
                    </div>
                    <CheckCircle2
                        class="w-5 h-5 shrink-0"
                        :class="form.municipality_type === 'other' ? 'text-[#0275B8]' : 'text-slate-300'"
                        aria-hidden="true"
                    />
                </button>
            </div>
            <InputError :message="form.errors.municipality_type" />

            <div v-if="form.municipality_type === 'other'" class="pt-1">
                <InputLabel for="other_municipality" value="Nombre de la localidad o municipio *" />
                <TextInput
                    id="other_municipality"
                    type="text"
                    v-model="form.other_municipality"
                    required
                    placeholder="Ej. Sahcabá, Xocchel, Sanahcat..."
                    class="mt-1.5 block w-full min-h-[44px] rounded-xl border-[#E9E7F0] text-sm"
                />
                <InputError class="mt-1" :message="form.errors.other_municipality" />
            </div>
        </div>

        <!-- 2. Zona de reparto -->
        <div class="space-y-2.5">
            <InputLabel for="delivery_zone" value="Zona de reparto *" class="text-[#1E1545] font-bold" />

            <div
                v-if="zoneEntries.length > 0"
                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5"
                role="radiogroup"
                aria-label="Selecciona tu zona de entrega"
            >
                <button
                    v-for="[key, label] in zoneEntries"
                    :key="key"
                    type="button"
                    role="radio"
                    :aria-checked="form.delivery_zone === key"
                    @click="form.delivery_zone = key"
                    class="min-w-0 px-3.5 py-2.5 min-h-[44px] rounded-xl border text-xs sm:text-sm font-bold text-left transition flex items-center gap-2 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9]"
                    :class="form.delivery_zone === key
                        ? 'bg-emerald-50 border-emerald-600 text-emerald-950 ring-1 ring-emerald-500/40'
                        : 'bg-white border-[#E9E7F0] text-slate-700 hover:bg-[#F5F4F8]'"
                >
                    <Navigation
                        class="w-3.5 h-3.5 shrink-0"
                        :class="form.delivery_zone === key ? 'text-emerald-600' : 'text-slate-400'"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ label }}</span>
                </button>
            </div>

            <select
                v-else
                id="delivery_zone"
                v-model="form.delivery_zone"
                class="mt-1 block w-full min-h-[44px] rounded-xl border-[#E9E7F0] text-sm text-[#1E1545] focus:border-[#0275B8] focus:ring-[#0275B8]"
            >
                <option value="">Selecciona una zona...</option>
            </select>
            <InputError :message="form.errors.delivery_zone" />
        </div>

        <!-- 3. Calle y número -->
        <div>
            <InputLabel for="street_and_number" value="Calle, número y cruzamientos *" class="text-[#1E1545] font-bold" />
            <TextInput
                id="street_and_number"
                type="text"
                v-model="form.street_and_number"
                required
                placeholder="Ej. Calle 20 #105 x 19 y 21"
                class="mt-1.5 block w-full min-h-[44px] rounded-xl border-[#E9E7F0] text-sm"
            />
            <p class="text-xs text-slate-500 mt-1">
                Incluye calle principal, número de casa y entre qué calles se ubica.
            </p>
            <InputError class="mt-1" :message="form.errors.street_and_number" />
        </div>

        <!-- 4. Referencias -->
        <div>
            <InputLabel for="reference" value="Referencias para el repartidor (opcional)" class="text-[#1E1545] font-bold" />
            <textarea
                id="reference"
                v-model="form.reference"
                rows="3"
                maxlength="300"
                placeholder="Ej. Casa color blanco con reja negra frente al parque"
                class="mt-1.5 block w-full rounded-xl border-[#E9E7F0] bg-[#F5F4F8]/40 text-base sm:text-sm text-[#1E1545] placeholder:text-slate-400 focus:border-[#6D28D9] focus:ring-[#6D28D9]"
            ></textarea>
            <InputError class="mt-1" :message="form.errors.reference" />
        </div>

        <!-- Botones -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-4 border-t border-[#E9E7F0]">
            <Link
                :href="cancelHref"
                class="inline-flex items-center justify-center px-5 py-2.5 min-h-[44px] rounded-xl text-sm font-bold text-slate-700 bg-[#F5F4F8] hover:bg-[#ECEAEF] border border-[#E9E7F0] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0275B8]"
            >
                Cancelar
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 min-h-[44px] rounded-xl text-sm font-extrabold text-white bg-[#1E1545] hover:bg-[#2E2363] shadow-sm transition cursor-pointer disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6D28D9] focus-visible:ring-offset-2"
            >
                <Save class="w-4 h-4" aria-hidden="true" />
                <span>{{ form.processing ? submittingLabel : submitLabel }}</span>
            </button>
        </div>
    </form>
</template>
