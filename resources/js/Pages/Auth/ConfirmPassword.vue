<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirmar Contraseña - Puritronic" />

        <div class="text-center mb-6">
            <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                Área Protegida
            </h1>
            <p class="mt-2 text-xs sm:text-sm text-slate-500 leading-relaxed">
                Esta es una sección segura del sistema. Por favor confirma tu contraseña actual para continuar.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="password" value="Contraseña Actual" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full"
                    v-model="form.password"
                    placeholder="Ingresa tu contraseña"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-3 text-sm font-bold shadow-md shadow-blue-900/10"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Verificando...</span>
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <i class="fa-solid fa-lock text-xs"></i>
                        <span>Confirmar Contraseña</span>
                    </span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
